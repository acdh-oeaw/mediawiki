<?php
/**
 * Implements Special:ListBots
 *
 * Copyright © 2004 Brooke Vibber, lcrocker, Tim Starling,
 * Domas Mituzas, Antoine Musso, Jens Frank, Zhengzhu,
 * 2006 Rob Church <robchur@gmail.com>
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
 * @ingroup SpecialPage
 */

namespace MediaWiki\Specials\Redirects;

use MediaWiki\SpecialPage\SpecialRedirectToSpecial;

/**
 * Redirect page: Special:ListBots --> Special:ListUsers/bot.
 *
 * @ingroup SpecialPage
 */
class SpecialListBots extends SpecialRedirectToSpecial {
	public function __construct() {
		parent::__construct( 'Listbots', 'Listusers', 'bot' );
	}
}

/**
 * @deprecated since 1.41
 */
class_alias( SpecialListBots::class, 'SpecialListBots' );
