const gulp = require('gulp');
const replace = require('gulp-replace');
const plumber = require('gulp-plumber');
const cleanCSS = require('gulp-clean-css');
const concat = require('gulp-concat');
const sourcemaps = require('gulp-sourcemaps');
const uglify = require('gulp-uglify');
const through2 = require('through2');
// Note: 'del' is imported dynamically inside the 'clean' task below.

// --- THEME-SPECIFIC CONFIGURATION ---
// FIX: Set to '.' because Gulp is run from inside the 'sieben' directory.
const THEME_DEV_DIR = '.'; 
const THEME_DIST_DIR = 'sieben_dist';
const WP_THEMES_PATH = 'C:/laragon/www/x-sieben/wp-content/themes/';
const DIST_ROOT = WP_THEMES_PATH + THEME_DIST_DIR;

// Define paths
const paths = {
    css: {
        // Source CSS files relative to the current Gulpfile location
        src: [
            `${THEME_DEV_DIR}/css/**/*.css`,
            `${THEME_DEV_DIR}/inc/**/*.css`,
        ],
        dest: DIST_ROOT + '/css'
    },
    js: {
        // Source JS files relative to the current Gulpfile location
        src: [
            `${THEME_DEV_DIR}/js/**/*.js`,
            `${THEME_DEV_DIR}/inc/**/*.js`,
        ],
        dest: DIST_ROOT + '/js'
    },
    php: {
        // Source PHP files, excluding the dev CSS/JS/Gulp files
        src: [
            `${THEME_DEV_DIR}/**/*.php`,
            `!${THEME_DEV_DIR}/css/**/*`, 
            `!${THEME_DEV_DIR}/js/**/*`, 
            `${THEME_DEV_DIR}/style.css`, 
            `!${THEME_DEV_DIR}/gulpfile.js`,
            `!${THEME_DEV_DIR}/package.json`,
            `!${THEME_DEV_DIR}/package-lock.json`,
            `!${THEME_DEV_DIR}/node_modules/**/*`,
        ],
        dest: DIST_ROOT + '/'
    },
    assets: {
        // Currently empty, but ready for uncommenting/adding files
        src: [
             // `${THEME_DEV_DIR}/assets/images/**/*`,
             // `${THEME_DEV_DIR}/assets/fonts/**/*`,
        ],
        dest: DIST_ROOT + '/assets'
    }
};

// Task to delete the distribution directory (Fixed for ESM)
async function clean() {
    const { deleteAsync } = await import('del'); 
    return deleteAsync([DIST_ROOT + '/**'], {force: true}); 
}


// Task to copy general assets (images, fonts, etc.) (Fixed for empty src array)
function copyAssets() {
    // Check if the source array is empty to prevent "Invalid glob argument" error.
    if (paths.assets.src.length === 0) {
        console.log('[Gulp] Skipping copyAssets: paths.assets.src is empty.');
        // Return a valid, empty stream to end the task gracefully.
        return gulp.src('.', {allowEmpty: true}); 
    }
    
    return gulp.src(paths.assets.src, { base: THEME_DEV_DIR + '/assets' })
        .pipe(gulp.dest(paths.assets.dest));
}


// Task to process and minify CSS safely
function styles() {
    return gulp.src(paths.css.src)
        .pipe(plumber())
        .pipe(sourcemaps.init())
        .pipe(concat('minified.css'))
        .pipe(cleanCSS({ 
            level: { 
                1: { specialComments: 0 }, 
                2: { all: false } 
            },
            format: 'keep-breaks' 
        }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.css.dest));
}

// Task to process and minify JS safely
function scripts() {
    return gulp.src(paths.js.src)
        .pipe(plumber())
        .pipe(sourcemaps.init())
        .pipe(concat('minified.js'))
        .pipe(uglify())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.js.dest));
}

// Task to clean and process PHP files
function processPHP() {
    return gulp.src(paths.php.src, { allowEmpty: true })
        .pipe(plumber()) 
        .pipe(replace(/\?>\s*$/g, '')) 
        .pipe(through2.obj((file, enc, cb) => {
            if (file.isBuffer()) {
                let content = file.contents.toString();
                // Remove excessive line breaks and trim content
                content = content.replace(/(\r?\n){2,}/g, '\n').trim(); 
                file.contents = Buffer.from(content);
            }
            cb(null, file);
        }, {allowEmpty: true})) 
        .pipe(gulp.dest(paths.php.dest)); 
}

// Watch files for changes
function watchFiles() {
    gulp.watch(paths.css.src, styles); 
    gulp.watch(paths.js.src, scripts); 
    gulp.watch(paths.php.src, processPHP);
    // Only watch assets if the src array is not empty
    if (paths.assets.src.length > 0) {
        gulp.watch(paths.assets.src, copyAssets);
    }
}

// Define the full 'build' task: clean, then build all assets
const build = gulp.series(clean, copyAssets, styles, scripts, processPHP);

// Define default task: run the build once, then start watching
const defaultTask = gulp.series(build, watchFiles);

// Export tasks
exports.clean = clean;
exports.copyAssets = copyAssets;
exports.styles = styles;
exports.scripts = scripts;
exports.processPHP = processPHP;
exports.build = build; 
exports.watch = watchFiles; 
exports.default = defaultTask;