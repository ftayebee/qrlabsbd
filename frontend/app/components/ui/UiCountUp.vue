<script setup lang="ts">
/** Animates a number from its previous value. Renders the final value on the server and with reduced motion. */
const props = withDefaults(defineProps<{ value: number; duration?: number; format?: (n: number) => string }>(), { duration: 900 })

const display = ref(props.value)
const reduced = useReducedMotion()
let frame = 0

function animate(from: number, to: number) {
  cancelAnimationFrame(frame)
  if (reduced.value || from === to) { display.value = to; return }
  const start = performance.now()
  const step = (now: number) => {
    const t = Math.min(1, (now - start) / props.duration)
    const eased = 1 - (1 - t) ** 4
    display.value = from + (to - from) * eased
    if (t < 1) frame = requestAnimationFrame(step)
  }
  frame = requestAnimationFrame(step)
}

onMounted(() => animate(0, props.value))
watch(() => props.value, (to, from) => animate(from ?? 0, to))
onBeforeUnmount(() => cancelAnimationFrame(frame))

const text = computed(() => (props.format ?? ((n: number) => Math.round(n).toLocaleString()))(display.value))
</script>

<template>
  <span class="tabular-nums">{{ text }}</span>
</template>
