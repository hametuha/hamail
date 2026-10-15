import wpPlugin from '@wordpress/eslint-plugin';
import { createRequire } from 'node:module';
import globals from 'globals';

const require = createRequire( import.meta.url );

export default [
	{
		ignores: [ 'assets/**', 'vendor/**', 'node_modules/**', 'wordpress/**' ],
	},
	...wpPlugin.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
			globals: {
				...globals.browser,
				...globals.jquery,
			},
		},
		rules: {
			'no-alert': 'off',
			'object-shorthand': 'off',
			'space-before-function-paren': 'warn',
			'react-hooks/rules-of-hooks': 'off',
		},
	},
];
