<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: String,
  color: {
    type: String,
    default: 'teal',
  },
  sub: String,
  alert: Boolean,
})

const accentMap = {
  teal:   'bg-primary-500',
  orange: 'bg-orange-400',
  blue:   'bg-blue-500',
  red:    'bg-red-500',
  green:  'bg-emerald-500',
  purple: 'bg-purple-500',
}

const valueMap = {
  teal:   'text-gray-900',
  orange: 'text-gray-900',
  blue:   'text-gray-900',
  red:    'text-red-600',
  green:  'text-gray-900',
  purple: 'text-gray-900',
}
</script>

<template>
  <div class="bg-white border border-gray-100 rounded-xl p-5 flex items-start gap-4 hover:border-gray-200 transition-all duration-200 relative overflow-hidden">
    <!-- Accent left bar -->
    <div class="absolute left-0 top-4 bottom-4 w-0.5 rounded-full" :class="accentMap[color]"></div>

    <div class="pl-3 flex-1 min-w-0">
      <p class="text-xs font-medium text-gray-400 uppercase tracking-widest">{{ title }}</p>
      <p class="text-2xl font-semibold mt-1 truncate" :class="valueMap[color]">{{ value ?? '—' }}</p>
      <p v-if="sub" class="text-xs text-gray-400 mt-1">{{ sub }}</p>
    </div>

    <!-- Alert dot -->
    <div v-if="alert" class="mt-1 flex-shrink-0">
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-red-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
      </span>
    </div>
  </div>
</template>
