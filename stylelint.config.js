module.exports = {
	extends: [ '@wordpress/stylelint-config/scss' ],
	rules: {
		'value-keyword-case': [ 'lower', {
			ignoreProperties: [ 'font-family' ],
		} ],
		'rule-empty-line-before': null,
		'selector-class-pattern': null,
	},
};
