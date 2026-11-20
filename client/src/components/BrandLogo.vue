<script setup lang="ts">
/** The logo of TuMiniSúper: a corner shop (pulpería) with its striped awning, and the name beside it. */
withDefaults(defineProps<{ size?: number; tagline?: string | null; light?: boolean }>(), { size: 36, tagline: null, light: false })
</script>

<template>
  <div class="brand" :class="{ light }">
    <svg :width="size" :height="size" viewBox="0 0 48 48" aria-hidden="true">
      <rect x="6" y="20" width="36" height="26" rx="5" fill="#FBF7F1" stroke="#2F6B4F" stroke-width="3" />
      <path d="M4 20l4-12h32l4 12z" fill="#B4532A" />
      <path d="M14 8l-2 12M24 8v12M34 8l2 12" stroke="#FBF7F1" stroke-width="3" />
      <rect x="19" y="30" width="10" height="16" rx="2" fill="#2F6B4F" />
    </svg>
    <span class="brand-text">
      <span class="brand-name">Tu<b>MiniSúper</b></span>
      <small v-if="tagline">{{ tagline }}</small>
    </span>
  </div>
</template>

<style scoped>
.brand { display: inline-flex; align-items: center; gap: 0.55rem; white-space: nowrap; }
.brand-text { display: grid; line-height: 1.05; }
.brand-name { font-family: var(--font-display); font-weight: 800; font-size: 1.3rem; color: var(--naranja-fuerte); letter-spacing: -0.01em; }
.brand-name b { color: var(--verde-medio); font-weight: 800; }
.brand small { font-size: 0.72rem; font-weight: 700; color: var(--suave); }
.light .brand-name { color: #fbe3cf; }
.light .brand-name b { color: #fff; }
.light small { color: rgba(255, 255, 255, 0.8); }
[data-theme='dark'] .brand-name { color: #f0b48f; }
[data-theme='dark'] .brand-name b { color: #a7d7bb; }
</style>
