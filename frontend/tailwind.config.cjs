// tailwind.config.cjs
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.html',
    './src/**/*.{js,ts,jsx,tsx,vue}',
  ],
  theme: {
    extend: {
      colors: {
        primary: 'hsl(210, 70%, 50%)',
        secondary: 'hsl(340, 70%, 50%)',
        accent: 'hsl(45, 90%, 55%)',
        dark: '#1a1a1a',
        light: '#f5f5f5',
      },
    },
  },
  plugins: [],
};
