module.exports = {
	extends: [
		'stylelint-config-wordpress/scss',
	],
	rules: {
		'value-keyword-case': [ 'lower', {
			ignoreProperties: [ 'font-family' ],
		} ],
		'number-leading-zero': null,
		'rule-empty-line-before': null,
		'declaration-property-unit-whitelist': null,
	},
};
