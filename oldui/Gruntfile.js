module.exports = function(grunt) {
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		copy: {
			main: {
				files: [
					{expand: true, src: ['index.html'], dest: 'build/'},
				]
			}
		},
		useminPrepare: {
			html: ['index.html'],
			options: {
				dest: 'build'
			}
		},
		usemin: {
			html: ['build/index.html'],
		},
		htmlmin: {
			html: {
				files: {
					'build/index.html': 'build/index.html'
				}
			},
			options: {
				collapseWhitespace: true,
			}
		},
		ngtemplates:  {
			grader: {
				src: 'templates/*',
				cwd: '.',
				dest: 'build/assets/js/templates.js',
				options: {
					concat: 'build/assets/js/app.min.js',
					htmlmin: {
						collapseWhitespace: true
					}
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-usemin');
	grunt.loadNpmTasks('grunt-angular-templates');
	grunt.loadNpmTasks('grunt-contrib-htmlmin');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-uglify');

	grunt.registerTask('default', [
		'copy',
		'useminPrepare',
		'ngtemplates',
		'concat',
		'cssmin', 'uglify',
		'usemin',
		'htmlmin'
	]);

};