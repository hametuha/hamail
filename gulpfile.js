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
    .pipe($.autoprefixer({browsers: ['last 2 version', '> 5%']}))
    .pipe($.sourcemaps.write('./map'))
    .pipe(gulp.dest('./assets/css'));
});


// Build JS
gulp.task('jsBundle', function () {
  return gulp.src(['./src/js/*.js'])
    .pipe($.plumber({
      errorHandler: $.notify.onError('<%= error.message %>')
    }))
    .pipe($.include())
    .pipe($.uglify())
    .on('error', $.util.log)
    .pipe(gulp.dest('./assets/js/'));
});

// List JS
gulp.task('eslint', function(){
  return gulp.src(['./src/js/**/*.js'])
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
  // return eventStream.merge(
  //   // Build unpacked Libraries.
  //   gulp.src([
  //     './node_modules/react/dist/react.min.js',
  //     './node_modules/react-dom/dist/react-dom.min.js'
  //   ])
  //     .pipe(gulp.dest('./assets/js/'))
  // );
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
  gulp.watch(['./src/js/**/*.js'], ['jsBundle']);
  // ESLint
  gulp.watch(['./src/js/**/*.jsx'], ['eslint']);
  // Minify Image
  gulp.watch('./src/img/**/*', ['imagemin']);
});


// Build
gulp.task('build', ['jsBundle', 'sass', 'imagemin']);

// Default Tasks
gulp.task('default', ['watch']);
