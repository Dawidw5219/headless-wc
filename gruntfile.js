require("dotenv").config();
module.exports = function (grunt) {
  grunt.loadNpmTasks("grunt-contrib-copy");
  grunt.loadNpmTasks("grunt-contrib-compress");
  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("grunt-contrib-cssmin");
  grunt.loadNpmTasks("grunt-contrib-uglify");
  grunt.loadNpmTasks("grunt-replace");
  grunt.loadNpmTasks("grunt-ftp-push");
  grunt.loadNpmTasks("grunt-sync");
  grunt.loadNpmTasks("grunt-wp-i18n");

  function packageJSON() {
    return grunt.file.readJSON("package.json");
  }

  grunt.initConfig({
    pkg: packageJSON(),

    ///////////////////////////////////
    wp_plugin_name: "<%= pkg.name %>",
    src_dir: "trunk",
    ///////////////////////////////////

    makepot: {
      target: {
        options: {
          type: "wp-plugin",
          potFilename: "<%= wp_plugin_name %>.pot",
          potHeaders: {
            poedit: true,
            "x-poedit-keywordslist": true,
          },
          updateTimestamp: true,
          cwd: "<%= src_dir %>",
          exclude: ["vendor/**", "node_modules/**"],
          domainPath: "/languages",
        },
      },
    },

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
            src: ["<%= src_dir %>/<%= wp_plugin_name %>.php"],
            dest: "<%= src_dir %>/<%= wp_plugin_name %>.php",
          },
        ],
      },
    },

    compress: {
      main: {
        options: {
          archive: "dist/<%= wp_plugin_name %>.zip",
        },
        files: [
          {
            expand: true,
            cwd: "<%= src_dir %>",
            src: ["**"],
            dest: "<%= wp_plugin_name %>/",
          },
        ],
      },
    },

    watch: {
      local: {
        files: ["<%= src_dir %>/**/*"],
        tasks: ["uglify", "cssmin", "sync"],
        options: {
          spawn: false,
        },
      },
      ftp: {
        files: ["<%= src_dir %>/**/*"],
        tasks: ["uglify", "cssmin", "ftp_push:deploy_updates"],
        options: {
          spawn: false,
        },
      },
    },

    sync: {
      main: {
        files: [
          {
            cwd: "<%= src_dir %>",
            src: ["**", "!**/.DS_Store"],
            dest: process.env.LOCAL_WP_PLUGINS_FOLDER_PATH + "/<%= wp_plugin_name %>",
          },
        ],
        updateAndDelete: true,
        compareUsing: "mtime",
      },
    },

    copy: {
      main: {
        expand: true,
        cwd: "<%= src_dir %>",
        src: "**",
        dest: process.env.LOCAL_WP_PLUGINS_FOLDER_PATH + "/<%= wp_plugin_name %>",
        flatten: false,
        filter: "isFile",
      },
    },

    ftp_push: {
      deploy_all: {
        options: {
          port: process.env.FTP_PORT || 21,
          host: process.env.FTP_HOST,
          username: process.env.FTP_USERNAME,
          password: process.env.FTP_PASSWORD,
          dest: process.env.FTP_WP_PLUGINS_FOLDER_PATH + "/<%= wp_plugin_name %>",
          incrementalUpdates: false,
        },
        files: [
          {
            expand: true,
            cwd: "<%= src_dir %>",
            src: ["**"],
          },
        ],
      },
      deploy_updates: {
        options: {
          port: process.env.FTP_PORT || 21,
          host: process.env.FTP_HOST,
          username: process.env.FTP_USERNAME,
          password: process.env.FTP_PASSWORD,
          dest: process.env.FTP_WP_PLUGINS_FOLDER_PATH + "/<%= wp_plugin_name %>",
          incrementalUpdates: true,
        },
        files: [
          {
            expand: true,
            cwd: "<%= src_dir %>",
            src: ["**"],
          },
        ],
      },
    },

    cssmin: {
      target: {
        files: [
          {
            expand: true,
            cwd: "<%= src_dir %>",
            src: ["**/*.css", "!**/vendor/**", "!**/*.min.css"],
            dest: "<%= src_dir %>/",
            ext: ".min.css",
          },
        ],
      },
    },

    uglify: {
      dev: {
        files: [
          {
            expand: true,
            src: ["**/*.js", "!**/vendor/**", "!**/node_modules/**", "!**/*.min.js"],
            dest: "<%= src_dir %>",
            cwd: "<%= src_dir %>",
            rename: function (dst, src) {
              const path = require("path");
              return path.join(dst, src.replace(".js", ".min.js"));
            },
          },
        ],
      },
    },
  });

  grunt.registerTask("dev:local", function () {
    grunt.task.run(["sync", "watch:local"]);
  });

  grunt.registerTask("dev:ftp", function () {
    grunt.task.run(["ftp_push:deploy_all", "watch:ftp"]);
  });

  grunt.registerTask("build", ["compress"]);
};
