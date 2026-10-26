<script setup lang="ts">
/** Fades content up once when it scrolls into view. Motion respects prefers-reduced-motion via <MotionConfig>. */
withDefaults(defineProps<{ delay?: number; y?: number; as?: 'div' | 'li' | 'section' | 'article' }>(), { delay: 0, y: 18, as: 'div' })
</script>

<template>
  <Motion
    :as="as"
    :initial="{ opacity: 0, y }"
    :while-in-view="{ opacity: 1, y: 0 }"
    :in-view-options="{ once: true, margin: '0px 0px -12% 0px' }"
    :transition="{ duration: 0.6, delay, ease: [0.25, 1, 0.5, 1] }"
  >
    <slot />
  </Motion>
</template>
