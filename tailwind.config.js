/** Tailwind config — brand primary palette (#f98007 solid). Rebuild: npx tailwindcss -i ./resources/css/app.css -o ./public/css/tailwind/app.css --minify */
const brand = {
  50: '#fff4e6',
  100: '#ffe8cc',
  200: '#ffd9b3',
  300: '#ffb84d',
  400: '#ffa033',
  500: '#f98007',
  600: '#e07206',
  700: '#f98007',
  800: '#f98007',
  900: '#ffa033',
  950: '#b85c00',
};

module.exports = {
  prefix: 'tw-',
  content: [
    './resources/views/**/*.blade.php',
    './Modules/**/Resources/views/**/*.blade.php',
    './public/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        primary: brand,
      },
    },
  },
  plugins: [],
};
