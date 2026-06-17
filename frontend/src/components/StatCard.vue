<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: String,
  color: {
    type: String,
    default: 'teal', // teal | orange | blue | red | green | purple
  },
  sub: String,
  alert: Boolean,
})

const colorMap = {
  teal:   { bg: 'bg-primary-50', icon: 'bg-primary-100 text-primary-700', value: 'text-primary-700', border: 'border-primary-200' },
  orange: { bg: 'bg-orange-50',  icon: 'bg-orange-100 text-orange-700',   value: 'text-orange-700',  border: 'border-orange-200' },
  blue:   { bg: 'bg-blue-50',    icon: 'bg-blue-100 text-blue-700',       value: 'text-blue-700',    border: 'border-blue-200' },
  red:    { bg: 'bg-red-50',     icon: 'bg-red-100 text-red-700',         value: 'text-red-700',     border: 'border-red-200' },
  green:  { bg: 'bg-green-50',   icon: 'bg-green-100 text-green-700',     value: 'text-green-700',   border: 'border-green-200' },
  purple: { bg: 'bg-purple-50',  icon: 'bg-purple-100 text-purple-700',   value: 'text-purple-700',  border: 'border-purple-200' },
}
</script>

<template>
  <div
    class="card p-5 flex items-center gap-4 transition-all duration-200 hover:shadow-md"
    :class="[colorMap[color].bg, 'border', colorMap[color].border]"
  >
    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl flex-shrink-0"
         :class="colorMap[color].icon">
      {{ icon }}
    </div>
    <div class="min-w-0">
      <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ title }}</p>
      <p class="text-2xl font-bold mt-0.5 truncate" :class="colorMap[color].value">{{ value }}</p>
      <p v-if="sub" class="text-xs text-gray-500 mt-0.5">{{ sub }}</p>
    </div>
    <div v-if="alert" class="ml-auto">
      <span class="flex h-3 w-3">
        <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-red-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
      </span>
    </div>
  </div>
</template>
