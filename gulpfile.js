const gulp = require( 'gulp' );
const $ = require( 'gulp-load-plugins' )();
const mozjpeg = require( 'imagemin-mozjpeg' );
const pngquant = require( 'imagemin-pngquant' );
const mergeStream = require( 'merge-stream' );
const webpack = require( 'webpack-stream' );
const webpackBundle = require( 'webpack' );
const named = require( 'vinyl-named' );

//
// SCSS tasks
// ================
//

// Lint SCSS
gulp.task( 'scss:lint', function () {
	return gulp.src( './src/scss/**/*.scss' )
		.pipe( $.plumber( {
			errorHandler: $.notify.onError( 'Stylelint: <%= error.message %>' ),
		} ) )
		.pipe( $.stylelint( {
			reporters: [
				{
					formatter: 'string',
					console: true,
				},
			],
		} ) );
} );

gulp.task( 'scss:generate', function () {
	const includePath = [
		'./src/scss',
	];
	return gulp.src( [
		'./src/scss/**/*.scss',
	] )
		.pipe( $.plumber( {
			errorHandler: $.notify.onError( 'SCSS: <%= error.message %>' ),
		} ) )
		.pipe( $.sourcemaps.init( { loadMaps: true } ) )
		.pipe( $.sassGlob() )
		.pipe( $.sass( {
			errLogToConsole: true,
			outputStyle: 'compressed',
			includePaths: includePath,
		} ) )
		.pipe( $.autoprefixer() )
		.pipe( $.sourcemaps.write( './map' ) )
		.pipe( gulp.dest( './assets/css' ) );
} );

gulp.task( 'scss', gulp.parallel( 'scss:generate', 'scss:lint' ) );

//
// JS Bundle
// ===============
//

// Minify All
gulp.task( 'js:bundle', function () {
	const tmp = {};
	return gulp.src( [ './src/js/**/*.js' ] )
		.pipe( $.plumber( {
			errorHandler: $.notify.onError( '<%= error.message %>' ),
		} ) )
		.pipe( named() )
		.pipe( $.rename( function ( path ) {
			tmp[ path.basename ] = path.dirname;
		} ) )
		.pipe( webpack( require( './webpack.config.js' ), webpackBundle ) )
		.pipe( $.rename( function ( path ) {
			if ( tmp[ path.basename ] ) {
				path.dirname = tmp[ path.basename ];
			} else if ( '.map' === path.extname && tmp[ path.basename.replace( /\.js$/, '' ) ] ) {
				path.dirname = tmp[ path.basename.replace( /\.js$/, '' ) ];
			}
			return path;
		} ) )
		.pipe( gulp.dest( './assets/js/' ) );
} );

// ESLint
gulp.task( 'js:eslint', function () {
	return gulp.src( [ 'src/**/*.js' ] )
		.pipe( $.eslint( { useEslintrc: true } ) )
		.pipe( $.eslint.format() );
} );

// JS task.
gulp.task( 'js', gulp.parallel( 'js:bundle', 'js:eslint' ) );

//
// Copy Library
// ==============
//

// Just copy.
gulp.task( 'copy', function () {
	return mergeStream(
		gulp.src( [
			'./node_modules/bootstrap/dist/js/bootstrap.min.js',
			'./node_modules/bootstrap/dist/js/bootstrap.min.js.map',
			'./node_modules/popper.js/dist/umd/popper.min.js',
			'./node_modules/popper.js/dist/umd/popper.min.js.map',
			'./node_modules/swiper/js/swiper.min.js',
			'./node_modules/swiper/js/swiper.min.js.map',
		] )
			.pipe( gulp.dest( 'assets/js' ) )
	);
} );

//
// Image min
// ==============
//

// SVG Minify and copy
gulp.task( 'imagemin:svg', function () {
	return gulp.src( './src/img/**/*.svg' )
		.pipe( $.svgmin() )
		.pipe( gulp.dest( './assets/icon ' ) );
} );

// Image min
gulp.task( 'imagemin:misc', function () {
	return gulp.src( [
		'./src/img/**/*',
		'!./src/img/**/*.svg',
	] )
		.pipe( $.imagemin( [
			pngquant( {
				quality: [ .65, .8 ],
				speed: 1,
				floyd: 0,
			} ),
			mozjpeg( {
				quality: 85,
				progressive: true,
			} ),
			$.imagemin.svgo(),
			$.imagemin.optipng(),
			$.imagemin.gifsicle(),
		] ) )
		.pipe( gulp.dest( './assets/img' ) );
} );

// minify all images.
gulp.task( 'imagemin', gulp.parallel( 'imagemin:misc', 'imagemin:svg' ) );

//
// Watch
// =================
//
gulp.task( 'watch', function () {
	// Make SASS
	gulp.watch( [
		'src/scss/**/*.scss',
	], gulp.task( 'scss' ) );
	// JS
	gulp.watch( [ 'src/js/**/*.js' ], gulp.task( 'js' ) );
	// Minify Image
	gulp.watch( 'src/img/**/*', gulp.task( 'imagemin' ) );
} );

//
// Global commands.
// ================
//

// Build
gulp.task( 'build', gulp.parallel( 'copy', 'js:bundle', 'scss:generate', 'imagemin' ) );

// Default Tasks
gulp.task( 'default', gulp.task( 'watch' ) );
