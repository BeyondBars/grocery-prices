<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart, Legend, LinearScale, LineElement, PointElement, Tooltip, type ChartOptions, type TooltipItem } from 'chart.js'
import moment from 'moment'
import { formatPrice } from '@/utils/money'
import { storeColor } from '@/utils/store-colors'
import type { PriceHistorySeries, PriceUnit } from '@/types/pricing'

Chart.register(LinearScale, PointElement, LineElement, Tooltip, Legend)

/**
 * Price over time, one line per store. The x axis is real time (timestamps on a linear scale),
 * so a gap of months looks like months. Specials are drawn as diamonds.
 */
const props = defineProps<{ series: PriceHistorySeries[]; unit: PriceUnit | null; storeIds: number[] }>()

type Point = { x: number; y: number; promotion: boolean }

const DAY = 24 * 60 * 60 * 1000
const time = (date: string) => moment(date).valueOf()

const data = computed(() => ({
  datasets: props.series.map((series) => {
    const color = storeColor(series.store.id, props.storeIds)

    return {
      label: series.store.name,
      data: series.points.map((point): Point => ({ x: time(point.date), y: point.price_cents / 100, promotion: point.is_promotion })),
      pointStyle: series.points.map((point) => (point.is_promotion ? 'rectRot' : 'circle')),
      borderColor: color,
      backgroundColor: color,
      borderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 6,
      pointBorderColor: '#ffffff',
      pointBorderWidth: 2,
    }
  }),
}))

const range = computed(() => {
  const times = props.series.flatMap((series) => series.points.map((point) => time(point.date)))
  return { min: Math.min(...times) - DAY, max: Math.max(...times) + DAY }
})

const options = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'nearest', intersect: false, axis: 'x' },
  plugins: {
    legend: { position: 'top', align: 'start', labels: { usePointStyle: true, boxWidth: 8, color: '#334155', font: { size: 12 } } },
    tooltip: {
      callbacks: {
        title: (items: TooltipItem<'line'>[]) => moment((items[0].raw as Point).x).format('ddd D MMM YYYY'),
        label: (item: TooltipItem<'line'>) => {
          const point = item.raw as Point
          return ` ${item.dataset.label}: ${formatPrice(Math.round(point.y * 100), props.unit)}${point.promotion ? ' (special)' : ''}`
        },
      },
    },
  },
  scales: {
    x: {
      type: 'linear',
      min: range.value.min,
      max: range.value.max,
      grid: { display: false },
      ticks: { color: '#64748b', maxRotation: 0, autoSkipPadding: 16, callback: (value) => moment(Number(value)).format('D MMM') },
    },
    y: {
      grid: { color: '#e2e8f0' },
      border: { display: false },
      ticks: { color: '#64748b', callback: (value) => formatPrice(Math.round(Number(value) * 100), props.unit) },
    },
  },
}))
</script>

<template>
  <div class="h-72">
    <Line :data="data" :options="options" aria-label="Price history chart" role="img" />
  </div>
</template>
