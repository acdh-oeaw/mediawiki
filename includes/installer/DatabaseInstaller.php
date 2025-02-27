<?php

/**
 * DBMS-specific installation helper.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Installer
 */

namespace MediaWiki\Installer;

use Exception;
use MediaWiki\Html\Html;
use MediaWiki\Status\Status;
use MWException;
use MWLBFactory;
use RuntimeException;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DatabaseDomain;
use Wikimedia\Rdbms\DBConnectionError;
use Wikimedia\Rdbms\DBExpectedError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactorySingle;

/**
 * Base class for DBMS-specific installation helper classes.
 *
 * @ingroup Installer
 * @since 1.17
 */
abstract class DatabaseInstaller {

	/**
	 * The Installer object.
	 *
	 * @var WebInstaller
	 */
	public $parent;

	/**
	 * @var string Set by subclasses
	 */
	public static $minimumVersion;

	/**
	 * @var string Set by subclasses
	 */
	protected static $notMinimumVersionMessage;

	/**
	 * The database connection.
	 *
	 * @var Database
	 */
	public $db = null;

	/**
	 * Internal variables for installation.
	 *
	 * @var array
	 */
	protected $internalDefaults = [];

	/**
	 * Array of MW configuration globals this class uses.
	 *
	 * @var array
	 */
	protected $globalNames = [];

	/**
	 * Whether the provided version meets the necessary requirements for this type
	 *
	 * @param IDatabase $conn
	 * @return Status
	 * @since 1.30
	 */
	public static function meetsMinimumRequirement( IDatabase $conn ) {
		$serverVersion = $conn->getServerVersion();
		if ( version_compare( $serverVersion, static::$minimumVersion ) < 0 ) {
			return Status::newFatal(
				static::$notMinimumVersionMessage, static::$minimumVersion, $serverVersion
			);
		}

		return Status::newGood();
	}

	/**
	 * Return the internal name, e.g. 'mysql', or 'sqlite'.
	 */
	abstract public function getName();

	/**
	 * @return bool Returns true if the client library is compiled in.
	 */
	abstract public function isCompiled();

	/**
	 * Checks for installation prerequisites other than those checked by isCompiled()
	 * @since 1.19
	 * @return Status
	 */
	public function checkPrerequisites() {
		return Status::newGood();
	}

	/**
	 * Get HTML for a web form that configures this database. Configuration
	 * at this time should be the minimum needed to connect and test
	 * whether install or upgrade is required.
	 *
	 * If this is called, $this->parent can be assumed to be a WebInstaller.
	 */
	abstract public function getConnectForm();

	/**
	 * Set variables based on the request array, assuming it was submitted
	 * via the form returned by getConnectForm(). Validate the connection
	 * settings by attempting to connect with them.
	 *
	 * If this is called, $this->parent can be assumed to be a WebInstaller.
	 *
	 * @return Status
	 */
	abstract public function submitConnectForm();

	/**
	 * Get HTML for a web form that retrieves settings used for installation.
	 * $this->parent can be assumed to be a WebInstaller.
	 * If the DB type has no settings beyond those already configured with
	 * getConnectForm(), this should return false.
	 * @return string|false
	 */
	public function getSettingsForm() {
		return false;
	}

	/**
	 * Set variables based on the request array, assuming it was submitted via
	 * the form return by getSettingsForm().
	 *
	 * @return Status
	 */
	public function submitSettingsForm() {
		return Status::newGood();
	}

	/**
	 * Open a connection to the database using the administrative user/password
	 * currently defined in the session, without any caching. Returns a status
	 * object. On success, the status object will contain a Database object in
	 * its value member.
	 *
	 * @return Status
	 */
	abstract public function openConnection();

	/**
	 * Create the database and return a Status object indicating success or
	 * failure.
	 *
	 * @return Status
	 */
	abstract public function setupDatabase();

	/**
	 * Connect to the database using the administrative user/password currently
	 * defined in the session. Returns a status object. On success, the status
	 * object will contain a Database object in its value member.
	 *
	 * This will return a cached connection if one is available.
	 *
	 * @return Status
	 * @suppress PhanUndeclaredMethod
	 */
	public function getConnection() {
		if ( $this->db ) {
			return Status::newGood( $this->db );
		}

		$status = $this->openConnection();
		if ( $status->isOK() ) {
			$this->db = $status->value;
			// Enable autocommit
			$this->db->clearFlag( DBO_TRX );
			$this->db->commit( __METHOD__ );
		}

		return $status;
	}

	/**
	 * Apply a SQL source file to the database as part of running an installation step.
	 *
	 * @param string $sourceFileMethod
	 * @param string $stepName
	 * @param string|false $tableThatMustNotExist
	 * @return Status
	 */
	private function stepApplySourceFile(
		$sourceFileMethod,
		$stepName,
		$tableThatMustNotExist = false
	) {
		$status = $this->getConnection();
		if ( !$status->isOK() ) {
			return $status;
		}
		$this->selectDatabase( $this->db, $this->getVar( 'wgDBname' ) );

		if ( $tableThatMustNotExist && $this->db->tableExists( $tableThatMustNotExist, __METHOD__ ) ) {
			$status->warning( "config-$stepName-tables-exist" );
			$this->enableLB();

			return $status;
		}

		$this->db->setFlag( DBO_DDLMODE );
		$this->db->begin( __METHOD__ );

		$error = $this->db->sourceFile(
			call_user_func( [ $this, $sourceFileMethod ], $this->db )
		);
		if ( $error !== true ) {
			$this->db->reportQueryError( $error, 0, '', __METHOD__ );
			$this->db->rollback( __METHOD__ );
			$status->fatal( "config-$stepName-tables-failed", $error );
		} else {
			$this->db->commit( __METHOD__ );
		}
		// Resume normal operations
		if ( $status->isOK() ) {
			$this->enableLB();
		}

		return $status;
	}

	/**
	 * Create database tables from scratch from the automatically generated file
	 *
	 * @return Status
	 */
	public function createTables() {
		return $this->stepApplySourceFile( 'getGeneratedSchemaPath', 'install', 'archive' );
	}

	/**
	 * Create database tables from scratch.
	 *
	 * @return Status
	 */
	public function createManualTables() {
		return $this->stepApplySourceFile( 'getSchemaPath', 'install-manual' );
	}

	/**
	 * Insert update keys into table to prevent running unneeded updates.
	 *
	 * @return Status
	 */
	public function insertUpdateKeys() {
		return $this->stepApplySourceFile( 'getUpdateKeysPath', 'updates', false );
	}

	/**
	 * Return a path to the DBMS-specific SQL file if it exists,
	 * otherwise default SQL file
	 *
	 * @param IDatabase $db
	 * @param string $filename
	 * @return string
	 */
	private function getSqlFilePath( $db, $filename ) {
		global $IP;

		$dbmsSpecificFilePath = "$IP/maintenance/" . $db->getType() . "/$filename";
		if ( file_exists( $dbmsSpecificFilePath ) ) {
			return $dbmsSpecificFilePath;
		} else {
			return "$IP/maintenance/$filename";
		}
	}

	/**
	 * Return a path to the DBMS-specific schema file,
	 * otherwise default to tables.sql
	 *
	 * @param IDatabase $db
	 * @return string
	 */
	public function getSchemaPath( $db ) {
		return $this->getSqlFilePath( $db, 'tables.sql' );
	}

	/**
	 * Return a path to the DBMS-specific automatically generated schema file.
	 *
	 * @param IDatabase $db
	 * @return string
	 */
	public function getGeneratedSchemaPath( $db ) {
		return $this->getSqlFilePath( $db, 'tables-generated.sql' );
	}

	/**
	 * Return a path to the DBMS-specific update key file,
	 * otherwise default to update-keys.sql
	 *
	 * @param IDatabase $db
	 * @return string
	 */
	public function getUpdateKeysPath( $db ) {
		return $this->getSqlFilePath( $db, 'update-keys.sql' );
	}

	/**
	 * Create the tables for each extension the user enabled
	 * @return Status
	 */
	public function createExtensionTables() {
		$status = $this->getConnection();
		if ( !$status->isOK() ) {
			return $status;
		}
		$this->enableLB();

		// Now run updates to create tables for old extensions
		$updater = DatabaseUpdater::newForDB( $this->db );
		$updater->setAutoExtensionHookContainer( $this->parent->getAutoExtensionHookContainer() );
		$updater->doUpdates( [ 'extensions' ] );

		return $status;
	}

	/**
	 * Get the DBMS-specific options for LocalSettings.php generation.
	 *
	 * @return string
	 */
	abstract public function getLocalSettings();

	/**
	 * Override this to provide DBMS-specific schema variables, to be
	 * substituted into tables.sql and other schema files.
	 * @return array
	 */
	public function getSchemaVars() {
		return [];
	}

	/**
	 * Set appropriate schema variables in the current database connection.
	 *
	 * This should be called after any request data has been imported, but before
	 * any write operations to the database.
	 */
	public function setupSchemaVars() {
		$status = $this->getConnection();
		if ( $status->isOK() ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$status->value->setSchemaVars( $this->getSchemaVars() );
		} else {
			$msg = __METHOD__ . ': unexpected error while establishing'
				. ' a database connection with message: '
				. $status->getMessage()->plain();
			throw new RuntimeException( $msg );
		}
	}

	/**
	 * Set up LBFactory so that getPrimaryDatabase() etc. works.
	 * We set up a special LBFactory instance which returns the current
	 * installer connection.
	 */
	public function enableLB() {
		$status = $this->getConnection();
		if ( !$status->isOK() ) {
			throw new RuntimeException( __METHOD__ . ': unexpected DB connection error' );
		}
		$connection = $status->value;
		$virtualDomains = array_merge(
			$this->parent->getVirtualDomains(),
			MWLBFactory::CORE_VIRTUAL_DOMAINS
		);

		$this->parent->resetMediaWikiServices( null, [
			'DBLoadBalancerFactory' => static function () use ( $virtualDomains, $connection ) {
				return LBFactorySingle::newFromConnection(
					$connection,
					[ 'virtualDomains' => $virtualDomains ]
				);
			}
		] );
	}

	/**
	 * Perform database upgrades
	 *
	 * @return bool
	 * @suppress SecurityCheck-XSS Escaping provided by $this->outputHandler
	 */
	public function doUpgrade() {
		$this->setupSchemaVars();
		$this->enableLB();

		$ret = true;
		ob_start( [ $this, 'outputHandler' ] );
		$up = DatabaseUpdater::newForDB( $this->db );
		try {
			$up->doUpdates();
			$up->purgeCache();
		} catch ( MWException $e ) {
			// TODO: Remove special casing in favour of MWExceptionRenderer
			echo "\nAn error occurred:\n";
			echo $e->getText();
			$ret = false;
		} catch ( Exception $e ) {
			echo "\nAn error occurred:\n";
			echo $e->getMessage();
			$ret = false;
		}
		ob_end_flush();

		return $ret;
	}

	/**
	 * Allow DB installers a chance to make last-minute changes before installation
	 * occurs. This happens before setupDatabase() or createTables() is called, but
	 * long after the constructor. Helpful for things like modifying setup steps :)
	 */
	public function preInstall() {
	}

	/**
	 * Allow DB installers a chance to make checks before upgrade.
	 */
	public function preUpgrade() {
	}

	/**
	 * Get an array of MW configuration globals that will be configured by this class.
	 * @return array
	 */
	public function getGlobalNames() {
		return $this->globalNames;
	}

	/**
	 * Construct and initialise parent.
	 * This is typically only called from Installer::getDBInstaller()
	 * @param WebInstaller $parent
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Convenience function.
	 * Check if a named extension is present.
	 *
	 * @param string $name
	 * @return bool
	 */
	protected static function checkExtension( $name ) {
		return extension_loaded( $name );
	}

	/**
	 * Get the internationalised name for this DBMS.
	 * @return string
	 */
	public function getReadableName() {
		// Messages: config-type-mysql, config-type-postgres, config-type-sqlite
		return wfMessage( 'config-type-' . $this->getName() )->text();
	}

	/**
	 * Get a name=>value map of MW configuration globals for the default values.
	 * @return array
	 * @return-taint none
	 */
	public function getGlobalDefaults() {
		$defaults = [];
		foreach ( $this->getGlobalNames() as $var ) {
			if ( isset( $GLOBALS[$var] ) ) {
				$defaults[$var] = $GLOBALS[$var];
			}
		}
		return $defaults;
	}

	/**
	 * Get a name=>value map of internal variables used during installation.
	 * @return array
	 */
	public function getInternalDefaults() {
		return $this->internalDefaults;
	}

	/**
	 * Get a variable, taking local defaults into account.
	 * @param string $var
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function getVar( $var, $default = null ) {
		$defaults = $this->getGlobalDefaults();
		$internal = $this->getInternalDefaults();
		if ( isset( $defaults[$var] ) ) {
			$default = $defaults[$var];
		} elseif ( isset( $internal[$var] ) ) {
			$default = $internal[$var];
		}

		return $this->parent->getVar( $var, $default );
	}

	/**
	 * Convenience alias for $this->parent->setVar()
	 * @param string $name
	 * @param mixed $value
	 */
	public function setVar( $name, $value ) {
		$this->parent->setVar( $name, $value );
	}

	/**
	 * Get a labelled text box to configure a local variable.
	 *
	 * @param string $var
	 * @param string $label
	 * @param array $attribs
	 * @param string $helpData HTML
	 * @return string HTML
	 * @return-taint escaped
	 */
	public function getTextBox( $var, $label, $attribs = [], $helpData = "" ) {
		$name = $this->getName() . '_' . $var;
		$value = $this->getVar( $var );
		if ( !isset( $attribs ) ) {
			$attribs = [];
		}

		return $this->parent->getTextBox( [
			'var' => $var,
			'label' => $label,
			'attribs' => $attribs,
			'controlName' => $name,
			'value' => $value,
			'help' => $helpData
		] );
	}

	/**
	 * Get a labelled password box to configure a local variable.
	 * Implements password hiding.
	 *
	 * @param string $var
	 * @param string $label
	 * @param array $attribs
	 * @param string $helpData HTML
	 * @return string HTML
	 * @return-taint escaped
	 */
	public function getPasswordBox( $var, $label, $attribs = [], $helpData = "" ) {
		$name = $this->getName() . '_' . $var;
		$value = $this->getVar( $var );
		if ( !isset( $attribs ) ) {
			$attribs = [];
		}

		return $this->parent->getPasswordBox( [
			'var' => $var,
			'label' => $label,
			'attribs' => $attribs,
			'controlName' => $name,
			'value' => $value,
			'help' => $helpData
		] );
	}

	/**
	 * Get a labelled checkbox to configure a local boolean variable.
	 *
	 * @param string $var
	 * @param string $label
	 * @param array $attribs Optional.
	 * @param string $helpData Optional.
	 * @return string
	 */
	public function getCheckBox( $var, $label, $attribs = [], $helpData = "" ) {
		$name = $this->getName() . '_' . $var;
		$value = $this->getVar( $var );

		return $this->parent->getCheckBox( [
			'var' => $var,
			'label' => $label,
			'attribs' => $attribs,
			'controlName' => $name,
			'value' => $value,
			'help' => $helpData
		] );
	}

	/**
	 * Get a set of labelled radio buttons.
	 *
	 * @param array $params Parameters are:
	 *      var:            The variable to be configured (required)
	 *      label:          The message name for the label (required)
	 *      itemLabelPrefix: The message name prefix for the item labels (required)
	 *      values:         List of allowed values (required)
	 *      itemAttribs     Array of attribute arrays, outer key is the value name (optional)
	 *
	 * @return string
	 */
	public function getRadioSet( $params ) {
		$params['controlName'] = $this->getName() . '_' . $params['var'];
		$params['value'] = $this->getVar( $params['var'] );

		return $this->parent->getRadioSet( $params );
	}

	/**
	 * Convenience function to set variables based on form data.
	 * Assumes that variables containing "password" in the name are (potentially
	 * fake) passwords.
	 * @param array $varNames
	 * @return array
	 */
	public function setVarsFromRequest( $varNames ) {
		return $this->parent->setVarsFromRequest( $varNames, $this->getName() . '_' );
	}

	/**
	 * Determine whether an existing installation of MediaWiki is present in
	 * the configured administrative connection. Returns true if there is
	 * such a wiki, false if the database doesn't exist.
	 *
	 * Traditionally, this is done by testing for the existence of either
	 * the revision table or the cur table.
	 *
	 * @return bool
	 */
	public function needsUpgrade() {
		$status = $this->getConnection();
		if ( !$status->isOK() ) {
			return false;
		}

		try {
			$this->selectDatabase( $this->db, $this->getVar( 'wgDBname' ) );
		} catch ( DBConnectionError $e ) {
			// Don't catch DBConnectionError
			throw $e;
		} catch ( DBExpectedError $e ) {
			return false;
		}

		return $this->db->tableExists( 'cur', __METHOD__ ) ||
			$this->db->tableExists( 'revision', __METHOD__ );
	}

	/**
	 * Get a standard install-user fieldset.
	 *
	 * @return string
	 */
	public function getInstallUserBox() {
		return "<span class=\"cdx-card\"><span class=\"cdx-card__text\">" .
			Html::element(
				'span',
				[ 'class' => 'cdx-card__text__title' ],
				wfMessage( 'config-db-install-account' )->text()
			) .
			"<span class=\"cdx-card__text__description\">" .
			$this->getTextBox(
				'_InstallUser',
				'config-db-username',
				[ 'dir' => 'ltr' ],
				$this->parent->getHelpBox( 'config-db-install-username' )
			) .
			$this->getPasswordBox(
				'_InstallPassword',
				'config-db-password',
				[ 'dir' => 'ltr' ],
				$this->parent->getHelpBox( 'config-db-install-password' )
			) .
			"</span></span></span>";
	}

	/**
	 * Submit a standard install user fieldset.
	 * @return Status
	 */
	public function submitInstallUserBox() {
		$this->setVarsFromRequest( [ '_InstallUser', '_InstallPassword' ] );

		return Status::newGood();
	}

	/**
	 * Get a standard web-user fieldset
	 * @param string|false $noCreateMsg Message to display instead of the creation checkbox.
	 *   Set this to false to show a creation checkbox (default).
	 *
	 * @return string
	 */
	public function getWebUserBox( $noCreateMsg = false ) {
		$wrapperStyle = $this->getVar( '_SameAccount' ) ? 'display: none' : '';
		$s = "<span class=\"cdx-card\"><span class=\"cdx-card__text\">" .
			Html::element(
				'span',
				[ 'class' => 'cdx-card__text__title' ],
				wfMessage( 'config-db-web-account' )->text()
			) .
			$this->getCheckBox(
				'_SameAccount', 'config-db-web-account-same',
				[ 'class' => 'hideShowRadio cdx-checkbox__input', 'rel' => 'dbOtherAccount' ]
			) .
			Html::openElement( 'div', [ 'id' => 'dbOtherAccount', 'style' => $wrapperStyle ] ) .
			$this->getTextBox( 'wgDBuser', 'config-db-username' ) .
			$this->getPasswordBox( 'wgDBpassword', 'config-db-password' ) .
			$this->parent->getHelpBox( 'config-db-web-help' );
		if ( $noCreateMsg ) {
			$s .= Html::warningBox( wfMessage( $noCreateMsg )->plain(), 'config-warning-box' );
		} else {
			$s .= $this->getCheckBox( '_CreateDBAccount', 'config-db-web-create' );
		}
		$s .= Html::closeElement( 'div' ) . "</span></span></span>";

		return $s;
	}

	/**
	 * Submit the form from getWebUserBox().
	 *
	 * @return Status
	 */
	public function submitWebUserBox() {
		$this->setVarsFromRequest(
			[ 'wgDBuser', 'wgDBpassword', '_SameAccount', '_CreateDBAccount' ]
		);

		if ( $this->getVar( '_SameAccount' ) ) {
			$this->setVar( 'wgDBuser', $this->getVar( '_InstallUser' ) );
			$this->setVar( 'wgDBpassword', $this->getVar( '_InstallPassword' ) );
		}

		if ( $this->getVar( '_CreateDBAccount' ) && strval( $this->getVar( 'wgDBpassword' ) ) == '' ) {
			return Status::newFatal( 'config-db-password-empty', $this->getVar( 'wgDBuser' ) );
		}

		return Status::newGood();
	}

	/**
	 * Common function for databases that don't understand the MySQLish syntax of interwiki.list.
	 *
	 * @return Status
	 */
	public function populateInterwikiTable() {
		$status = $this->getConnection();
		if ( !$status->isOK() ) {
			return $status;
		}
		$this->selectDatabase( $this->db, $this->getVar( 'wgDBname' ) );

		$row = $this->db->newSelectQueryBuilder()
			->select( '1' )
			->from( 'interwiki' )
			->caller( __METHOD__ )->fetchRow();
		if ( $row ) {
			$status->warning( 'config-install-interwiki-exists' );

			return $status;
		}
		global $IP;
		AtEase::suppressWarnings();
		$rows = file( "$IP/maintenance/interwiki.list",
			FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		AtEase::restoreWarnings();
		if ( !$rows ) {
			return Status::newFatal( 'config-install-interwiki-list' );
		}
		$insert = $this->db->newInsertQueryBuilder()
			->insertInto( 'interwiki' );
		foreach ( $rows as $row ) {
			$row = preg_replace( '/^\s*([^#]*?)\s*(#.*)?$/', '\\1', $row ); // strip comments - whee
			if ( $row == "" ) {
				continue;
			}
			$row .= "|";
			$insert->row(
				array_combine(
					[ 'iw_prefix', 'iw_url', 'iw_local', 'iw_api', 'iw_wikiid' ],
					explode( '|', $row )
				)
			);
		}
		$insert->caller( __METHOD__ )->execute();

		return Status::newGood();
	}

	public function outputHandler( $string ) {
		return htmlspecialchars( $string );
	}

	/**
	 * @param Database $conn
	 * @param string $database
	 * @return bool
	 * @since 1.39
	 */
	protected function selectDatabase( Database $conn, string $database ) {
		$schema = $conn->dbSchema();
		$prefix = $conn->tablePrefix();

		$conn->selectDomain( new DatabaseDomain(
			$database,
			// DatabaseDomain uses null for unspecified schemas
			( $schema !== '' ) ? $schema : null,
			$prefix
		) );

		return true;
	}
}
