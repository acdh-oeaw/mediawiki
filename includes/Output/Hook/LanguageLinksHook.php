<?php

namespace MediaWiki\Output\Hook;

use MediaWiki\Title\Title;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "LanguageLinks" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface LanguageLinksHook {
	/**
	 * Use this hook to manipulate a page's language links. This hook is called
	 * in various places to allow extensions to define the effective language
	 * links for a page.
	 *
	 * @since 1.35
	 *
	 * @param Title $title
	 * @param string[] &$links Array with elements of the form "language:title" in the order
	 *   that they will be output
	 * @param array &$linkFlags Associative array mapping prefixed links to arrays of flags.
	 *   Currently unused, but planned to provide support for marking individual
	 *   language links in the UI, e.g. for featured articles.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLanguageLinks( $title, &$links, &$linkFlags );
}

/**
 * @deprecated since 1.42
 */
class_alias( LanguageLinksHook::class, 'MediaWiki\Hook\LanguageLinksHook' );
