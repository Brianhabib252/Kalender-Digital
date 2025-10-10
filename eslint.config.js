import antfu from '@antfu/eslint-config'

export default antfu({
  vue: true,
  typescript: false,
  formatters: {
    css: true,
    html: true,
    markdown: 'prettier',
  },
  rules: {
    'vue/custom-event-name-casing': ['error', 'kebab-case'],
    'style/max-statements-per-line': 'off',
  },
  ignores: [
    'storage/**/*',
    '**/*.{yaml,yml,php}',
    'resources/js/Components/ui/**/*',
    'public/**/*',
    'temp_original.vue',
  ],
})
