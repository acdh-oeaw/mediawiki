<?php

namespace MediaWiki\Output\Hook;

use MediaWiki\Output\OutputPage;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "AfterFinalPageOutput" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface AfterFinalPageOutputHook {
	/**
	 * This hook is called nearly at the end of OutputPage::output() but
	 * before OutputPage::sendCacheControl() and final ob_end_flush() which
	 * will send the buffered output to the client. This allows for last-minute
	 * modification of the output within the buffer by using ob_get_clean().
	 *
	 * @since 1.35
	 *
	 * @param OutputPage $output The OutputPage object where output() was called
	 * @return void This hook must not abort, it must return no value
	 */
	public function onAfterFinalPageOutput( $output ): void;
}

/**
 * @deprecated since 1.42
 */
class_alias( AfterFinalPageOutputHook::class, 'MediaWiki\Hook\AfterFinalPageOutputHook' );
