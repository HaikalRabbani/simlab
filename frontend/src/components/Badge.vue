<script setup>
import { computed } from 'vue'

const props = defineProps({
  text: { type: String, required: true },
  tone: { type: String, default: 'auto' }, // auto | danger | warning | success | neutral | teal
})

const tone = computed(() => {
  if (props.tone !== 'auto') return props.tone
  const t = props.text.toLowerCase()
  if (t.includes('reaktif') || t === 'positif' || t === 'pendampingan' || t === 'menunggu') return t.includes('menunggu') ? 'warning' : 'danger'
  if (t.includes('invalid') || t.includes('skrining ulang')) return 'warning'
  if (t.includes('terkirim') || t === 'selesai' || t.includes('pantau rutin') || t === 'negatif') return 'success'
  return 'neutral'
})

const styles = {
  danger: { color: '#be2b22', background: '#fbe7e5' },
  warning: { color: '#a96a0c', background: '#fcf0dc' },
  success: { color: '#1b7f4b', background: '#e4f2e9' },
  teal: { color: '#0b6e6e', background: '#e3f1f0' },
  neutral: { color: '#41607c', background: '#edf1f4' },
}
</script>

<template>
  <span class="badge" :style="styles[tone]"><slot>{{ text }}</slot></span>
</template>

<style scoped>
.badge {
  display: inline-block;
  border-radius: 999px;
  padding: 3px 11px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
</style>
