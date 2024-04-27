module.exports = function (grunt) {
  grunt.loadNpmTasks("grunt-contrib-copy");
  grunt.loadNpmTasks("grunt-contrib-compress");
  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("grunt-replace");

  function packageJSON() {
    return grunt.file.readJSON("package.json");
  }

  grunt.initConfig({
    pkg: packageJSON(),
    projectName: "<%= pkg.name %>",
    destDir: grunt.option("output"),

    replace: {
      main: {
        options: {
          patterns: [
            {
              match: /Version:\s*\d+\.\d+\.\d+/,
              replacement: function () {
                return "Version: " + packageJSON().version;
              },
            },
          ],
          usePrefix: false,
        },
        files: [
          {
            src: ["src/<%= projectName %>.php"],
            dest: "src/<%= projectName %>.php",
          },
        ],
      },
    },
    copy: {
      main: {
        expand: true,
        cwd: "src/",
        src: "**",
        dest: "<%= destDir %>/<%= projectName %>",
        flatten: false,
        filter: "isFile",
      },
    },
    compress: {
      main: {
        options: {
          archive: "dist/<%= projectName %>.zip",
        },
        files: [
          {
            expand: true,
            cwd: "src/",
            src: ["**"],
            dest: "<%= projectName %>/",
          },
        ],
      },
    },
    watch: {
      scripts: {
        files: ["src/**/*", "package.json"],
        tasks: ["replace", "copy"],
        options: {
          spawn: false,
        },
      },
    },
  });

  grunt.registerTask("default", function () {
    if (grunt.config.get("destDir")) {
      grunt.task.run(["replace", "copy", "watch"]);
    } else {
      grunt.fail.fatal("Destination directory must be provided for the dev mode. Use --output=PATH");
    }
  });

  grunt.registerTask("build", ["replace", "compress"]);
};
