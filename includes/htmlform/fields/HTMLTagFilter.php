<?php

namespace MediaWiki\HTMLForm\Field;

use ChangeTags;
use MediaWiki\HTMLForm\HTMLFormField;

/**
 * Wrapper for ChangeTags::buildTagFilterSelector to use in HTMLForm
 *
 * @stable to extend
 */
class HTMLTagFilter extends HTMLFormField {
	protected $tagFilter;

	public function getTableRow( $value ) {
		$this->tagFilter = ChangeTags::buildTagFilterSelector(
			$value, false, $this->mParent->getContext() );
		if ( $this->tagFilter ) {
			return parent::getTableRow( $value );
		}
		return '';
	}

	public function getDiv( $value ) {
		$this->tagFilter = ChangeTags::buildTagFilterSelector(
			$value, false, $this->mParent->getContext() );
		if ( $this->tagFilter ) {
			return parent::getDiv( $value );
		}
		return '';
	}

	public function getOOUI( $value ) {
		$this->tagFilter = ChangeTags::buildTagFilterSelector(
			$value, true, $this->mParent->getContext() );
		if ( $this->tagFilter ) {
			return parent::getOOUI( $value );
		}
		return new \OOUI\FieldLayout( new \OOUI\Widget() );
	}

	public function getInputHTML( $value ) {
		if ( $this->tagFilter ) {
			// we only need the select field, HTMLForm should handle the label
			return $this->tagFilter[1];
		}
		return '';
	}

	public function getInputOOUI( $value ) {
		if ( $this->tagFilter ) {
			// we only need the select field, HTMLForm should handle the label
			return $this->tagFilter[1];
		}
		return '';
	}

	protected function shouldInfuseOOUI() {
		return true;
	}
}

/** @deprecated since 1.42 */
class_alias( HTMLTagFilter::class, 'HTMLTagFilter' );
