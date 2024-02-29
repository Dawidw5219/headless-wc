module.exports = function (grunt) {
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),
    compress: {
      main: {
        options: {
          archive: "dist/headless-wc.zip",
        },
        files: [
          {
            expand: true,
            cwd: "src/",
            src: ["**"],
            dest: "headless-wc/",
          },
        ],
      },
    },
  });
  grunt.loadNpmTasks("grunt-contrib-compress");
  grunt.registerTask("default", ["compress"]);
};
