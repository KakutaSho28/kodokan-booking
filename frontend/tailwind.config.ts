import type { Config } from "tailwindcss";
import scrollbarHide from "tailwind-scrollbar-hide";

export default <Partial<Config>>{
  content: [
    "./components/**/*.{vue,js,ts}",
    "./layouts/**/*.vue",
    "./pages/**/*.vue",
    "./app.vue",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          600: "#2C5F8A",
          700: "#244F73",
          800: "#4A90B8",
        },
        surface: {
          50: "#F9FAFB",
        },
      },
      fontFamily: {
        sans: ["system-ui", "sans-serif"],
      },
      borderRadius: {
        lg: "8px",
      },
    },
  },
  plugins: [scrollbarHide],
};
