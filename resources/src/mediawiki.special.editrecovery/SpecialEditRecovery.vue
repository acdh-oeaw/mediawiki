<template>
	<p v-if="pages.length">
		{{ $i18n( 'edit-recovery-special-intro', pages.length ) }}
	</p>
	<p v-else>
		{{ $i18n( 'edit-recovery-special-intro-empty' ) }}
	</p>
	<ol>
		<li v-for="page in pages" :key="page">
			{{ page.title }}
			<span v-if="page.section"> &ndash; {{ page.section }}</span>
			{{ $i18n( 'parentheses-start' ) }}
			<a :href="page.url">{{ $i18n( 'edit-recovery-special-view' ) }}</a>
			{{ $i18n( 'pipe-separator' ) }}
			<a :href="page.editUrl">{{ $i18n( 'edit-recovery-special-edit' ) }}</a>
			{{ $i18n( 'parentheses-end' ) }}
			<span :title="$i18n( 'edit-recovery-special-recovered-on-tooltip' )">
				{{ $i18n('edit-recovery-special-recovered-on', page.timeStored ) }}
			</span>
		</li>
	</ol>
</template>

<script>
const { ref } = require( 'vue' );
// @vue/component
module.exports = {
	setup() {
		const pages = ref( [] );
		const moment = require( 'moment' );
		const storage = require( '../mediawiki.editRecovery/storage.js' );
		const config = require( '../mediawiki.editRecovery/config.json' );
		const expiryTTL = config.EditRecoveryExpiry;
		storage.openDatabase().then( () => {
			storage.loadAllData().then( ( allData ) => {
				allData.forEach( ( d ) => {
					const title = new mw.Title( d.pageName );
					const editParams = { action: 'edit' };
					if ( d.section ) {
						editParams.section = d.section;
					}
					// Subtract expiry duration to get the time it was stored.
					const recoveryTime = moment( ( d.expiry - expiryTTL ) * 1000 ).format( 'LLLL' );
					pages.value.push( {
						title: title.getPrefixedText(),
						url: title.getUrl(),
						editUrl: title.getUrl( editParams ),
						section: d.section,
						timeStored: recoveryTime
					} );
				} );
			} );
		} );
		return {
			pages
		};
	}
};
// eslint-disable-next-line vue/dot-location
</script>
