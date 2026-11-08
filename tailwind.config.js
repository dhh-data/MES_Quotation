/**
 * Tailwind CSS untuk MES Quotation.
 * Build: npm run build:css  (hasil: public/assets/css/tailwind.css)
 *
 * Palet diambil dari logo MES: hijau tua, hijau daun, lime, kuning.
 * "moss" adalah abu-abu netral yang condong ke hijau agar serasi dengan merek.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './src/Views/**/*.php',
    './src/Core/helpers.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'Menlo', 'Consolas', 'monospace'],
      },
      colors: {
        mes: {
          forest: '#05472f',
          green: '#177a41',
          leaf: '#5d9b14',
          lime: '#b4d000',
          yellow: '#f7e100',
        },
        moss: {
          50: '#f4f8f2',
          100: '#e8f0e4',
          200: '#d4e0cd',
          300: '#b5c7ab',
          400: '#8ea483',
          500: '#5f7766',
          600: '#4a6050',
          700: '#384c3f',
          800: '#1f3226',
          900: '#14231a',
          950: '#0b150f',
        },
      },
    },
  },
  plugins: [],
};
