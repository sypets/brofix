/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

module.exports = function (grunt) {

  const sass = require('node-sass');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    paths: {
        root: './',
        sass: '<%= paths.root %>Resources/Private/Sass/'
    },

    sass: {
      options: {
        implementation: sass,
        sourceMap: false
      },
      dist: {
        options: {
          outputStyle: 'compressed'
        },
        files: {
          'Resources/Public/Css/brofix.css': 'Resources/Private/Sass/brofix.scss',
          'Resources/Public/Css/brofix_manage_exclusions.css': 'Resources/Private/Sass/brofix_manage_exclusions.scss'
        }
      }
    },

    watch: {
      sass: {
        files: [
          'Resources/Private/Sass/brofix.scss',
          'Resources/Private/Sass/brofix_manage_exclusions.scss'
        ],
        tasks: [
          'sass'
        ]
      }
    }


  });

  //grunt.loadNpmTasks('grunt-sass');
  grunt.loadNpmTasks('grunt-sass');

  grunt.loadNpmTasks('grunt-contrib-watch');

  grunt.registerTask('watch', ['sass', 'watch']);

  grunt.registerTask('build', ['sass']);

};
