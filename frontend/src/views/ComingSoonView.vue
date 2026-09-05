<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>{{ route.meta.title }}</h1>
        <p class="page-subtitle">SIMLAB Jabar</p>
      </div>
    </div>
    <div class="card empty-state">
      <div style="font-size:30px;margin-bottom:8px">🚧</div>
      Halaman <b>{{ route.meta.title }}</b> sedang dalam pengembangan dan akan menyusul sesuai spesifikasi SIMLAB Jabar.
    </div>
  </div>
</template>
