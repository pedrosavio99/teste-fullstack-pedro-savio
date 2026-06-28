/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        // pilha de fontes do sistema Apple, com Inter como reforço
        sans: [
          '-apple-system', 'BlinkMacSystemFont', 'SF Pro Text',
          'Inter', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif',
        ],
      },
      colors: {
        // paleta inspirada no iOS (tons suaves + azul de sistema)
        ink: {
          DEFAULT: '#1d1d1f', // quase preto da Apple
          soft: '#6e6e73',    // cinza de texto secundário
        },
        system: {
          blue: '#0071e3',    // azul de ação da Apple
          blueHover: '#0077ed',
          green: '#34c759',
          red: '#ff3b30',
          orange: '#ff9500',
          gray: '#f5f5f7',    // fundo cinza claríssimo da Apple
        },
      },
      borderRadius: {
        'xl2': '1.25rem',
        '2xl2': '1.5rem',
      },
      boxShadow: {
        // sombras suaves, difusas, sem dureza
        'apple': '0 4px 20px rgba(0, 0, 0, 0.06)',
        'apple-md': '0 8px 30px rgba(0, 0, 0, 0.08)',
        'apple-lg': '0 12px 40px rgba(0, 0, 0, 0.10)',
      },
      backdropBlur: {
        'apple': '20px',
      },
      transitionTimingFunction: {
        // curva de animação típica da Apple
        'apple': 'cubic-bezier(0.28, 0.11, 0.32, 1)',
      },
    },
  },
  plugins: [],
}