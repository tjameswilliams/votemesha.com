'use strict';

var gulp = require('gulp');
var rename = require('gulp-rename');
var uglify = require('gulp-uglify');
var concat = require('gulp-concat');
var cleanCSS = require('gulp-clean-css');
var sass = require('gulp-sass');
var fs = require('fs');
var merge = require('merge-stream');
var browserSync = require('browser-sync').create();
var reload      = browserSync.reload;

gulp.task('minify_js', function() {
  var jsDeps = JSON.parse(fs.readFileSync('dependencies.json')).js;
  gulp.src(jsDeps)
    .pipe(concat('all.js'))
    //.pipe(uglify())
    .pipe(rename({ extname: '.min.js' }))
    .pipe(gulp.dest('dist'))
});

gulp.task('minify_css', function() {
  var deps = JSON.parse(fs.readFileSync('dependencies.json'));

  var cssStream = gulp.src(deps.css)
    .pipe(cleanCSS({compatibility: 'ie8'}));

  var scssStream = gulp.src(deps.scss)
    .pipe(sass().on('error', sass.logError));


  var mergedStreams = merge(cssStream,scssStream)
    .pipe(concat('all.css'))
    .pipe(rename({ extname: '.min.css' }))
    .pipe(gulp.dest('dist/'))

    return mergedStreams;
});

gulp.task('watch', function () {

  if( fs.existsSync('env.dev.json') ) {
    var devEnv = JSON.parse(fs.readFileSync('env.dev.json'));
    browserSync.init(devEnv);
    gulp.watch([
      __dirname+"/dist/all.min.css",
      __dirname+"/dist/all.min.js",
      __dirname+"/*.php",
      __dirname+"/**/*.php"
    ]).on("change", reload);
  }

  gulp.watch([
    __dirname+"/src/**/*.scss",
    __dirname+'/src/**/*.css'
  ], ['minify_css']);

  gulp.watch(__dirname+'/src/js/*.js', ['minify_js']);
});

gulp.task('default', ['minify_js','minify_css']);
