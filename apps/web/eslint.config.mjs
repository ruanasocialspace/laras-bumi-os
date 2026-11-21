import { defineConfig, globalIgnores } from "eslint/config";
import js from "@eslint/js";
import next from "@next/eslint-plugin-next";
import hooks from "eslint-plugin-react-hooks";
import typescript from "typescript-eslint";

export default defineConfig([
  js.configs.recommended,
  ...typescript.configs.recommended,
  next.configs["core-web-vitals"],
  hooks.configs.flat.recommended,
  globalIgnores([".next/**", "next-env.d.ts", "playwright-report/**", "test-results/**"]),
]);
