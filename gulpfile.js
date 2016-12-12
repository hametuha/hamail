var gulp        = require('gulp'),
    fs          = require('fs'),
    $           = require('gulp-load-plugins')(),
    pngquant    = require('imagemin-pngquant'),
    eventStream = require('event-stream');


// Sass staks
gulp.task('sass', function () {
  return gulp.src(['./src/scss/**/*.scss'])
    .pipe($.plumber({
      errorHandler: $.notify.onError('<%= error.message %>')
    }))
    .pipe($.sourcemaps.init({loadMaps: true}))
    .pipe($.sassBulkImport())
    .pipe($.sass({
      errLogToConsole: true,
      outputStyle    : 'compressed',
      includePaths   : [
        './src/scss'
      ]
    }))
    .pipe($.autoprefixer({browser: ['last 2 version', '> 5%']}))
    .pipe($.sourcemaps.write('./map'))
    .pipe(gulp.dest('./assets/css'));
});


// Build JSX
gulp.task('jsx', function () {
  return gulp.src(['./src/js/**/*.jsx'])
    .pipe($.sourcemaps.init({
      loadMaps: true
    }))
    .pipe($.babel({
      presets: ['es2015', 'react']
    }))
    .pipe($.uglify())
    .on('error', $.util.log)
    .pipe($.sourcemaps.write('./map'))
    .pipe(gulp.dest('./assets/js/'));
});

gulp.task('eslint', function(){
  return gulp.src(['./src/js/**/*.jsx'])
    .pipe($.plumber({
      errorHandler: $.notify.onError('<%= error.message %>')
    }))
    .pipe($.eslint({
      configFile: './.eslintrc.json'
    }))
    .pipe($.eslint.format());
});

// Build Libraries
gulp.task('copylib', function () {
  return eventStream.merge(
    // Build unpacked Libraries.
    gulp.src([
      './node_modules/react/dist/react.js',
      './node_modules/react-dom/dist/react-dom.js'
    ])
      .pipe($.uglify())
      .pipe(gulp.dest('./assets/js/'))
  );
});

// Image min
gulp.task('imagemin', function () {
  return gulp.src('./src/img/**/*')
    .pipe($.imagemin({
      progressive: true,
      svgoPlugins: [{removeViewBox: false}],
      use        : [pngquant()]
    }))
    .pipe(gulp.dest('./assets/img'));
});


// watch
gulp.task('watch', function () {
  // Make SASS
  gulp.watch('./src/scss/**/*.scss', ['sass']);
  // JSX
  gulp.watch(['./src/js/**/*.jsx'], ['jsx']);
  // ESLint
  gulp.watch(['./src/js/**/*.jsx'], ['eslint']);
  // Minify Image
  gulp.watch('./src/img/**/*', ['imagemin']);
});


// Build
gulp.task('build', ['copylib', 'jsx', 'sass', 'imagemin']);

// Default Tasks
gulp.task('default', ['watch']);
