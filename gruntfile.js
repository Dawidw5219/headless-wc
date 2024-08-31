require("dotenv").config();
module.exports = function (grunt) {
  grunt.loadNpmTasks("grunt-contrib-copy");
  grunt.loadNpmTasks("grunt-contrib-compress");
  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("grunt-replace");
  grunt.loadNpmTasks("grunt-ftp-push");

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
      local: {
        files: ["src/**/*", "package.json"],
        tasks: ["replace", "copy"],
        options: {
          spawn: false,
        },
      },
      ftp: {
        files: ["trunk/**/*"],
        tasks: ["ftp_push:deployUpdates"],
        options: {
          spawn: false,
        },
      },
    },
    ftp_push: {
      deployAll: {
        options: {
          port: process.env.FTP_PORT || 21,
          host: process.env.FTP_HOST,
          username: process.env.FTP_USERNAME,
          password: process.env.FTP_PASSWORD,
          dest: process.env.FTP_DESTINATION_PATH,
          incrementalUpdates: false,
        },
        files: [
          {
            expand: true,
            cwd: "trunk",
            src: ["**"],
          },
        ],
      },
      deployUpdates: {
        options: {
          port: process.env.FTP_PORT || 21,
          host: process.env.FTP_HOST,
          username: process.env.FTP_USERNAME,
          password: process.env.FTP_PASSWORD,
          dest: process.env.FTP_DESTINATION_PATH,
          incrementalUpdates: true,
        },
        files: [
          {
            expand: true,
            cwd: "trunk",
            src: ["**"],
          },
        ],
      },
    },
  });

  grunt.registerTask("dev:local", function () {
    if (grunt.config.get("destDir")) {
      grunt.task.run(["replace", "copy", "watch:local"]);
    } else {
      grunt.fail.fatal("Destination directory must be provided for the dev mode. Use --output=PATH");
    }
  });

  grunt.registerTask("dev:ftp", function () {
    grunt.task.run(["ftp_push:deployAll", "watch:ftp"]);
  });

  grunt.registerTask("build", ["replace", "compress"]);
};
