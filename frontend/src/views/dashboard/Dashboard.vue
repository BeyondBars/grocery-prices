<template>
  <PageHeader :title="`Welcome back${loggedInUser.first_name ? `, ${loggedInUser.first_name}` : ''}`" description="Your grocery prices at a glance.">
    <RouterLink :to="{ name: 'receipts' }" class="btn btn-primary"><FontAwesomeIcon icon="cloud-arrow-up" /> Upload receipts</RouterLink>
  </PageHeader>

  <template v-if="data">
    <!-- First run -->
    <div v-if="!data.counts.receipts" class="card flex flex-col items-center gap-3 px-6 py-12 text-center">
      <span class="flex size-14 items-center justify-center rounded-full bg-brand-50 text-2xl text-brand-600"><FontAwesomeIcon icon="receipt" /></span>
      <h2 class="text-lg font-semibold text-slate-900">Upload your first receipt</h2>
      <p class="max-w-md text-sm text-slate-500">Photos or PDFs of your grocery receipts become a price history, so you can see where each product is cheapest.</p>
      <RouterLink :to="{ name: 'receipts' }" class="btn btn-primary mt-2">Upload receipts</RouterLink>
    </div>

    <div v-else class="space-y-6">
      <RouterLink v-if="data.counts.needs_review" :to="{ name: 'receipts' }" class="card flex items-center gap-3 border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 hover:bg-amber-100">
        <FontAwesomeIcon icon="triangle-exclamation" />
        <span class="flex-1">{{ data.counts.needs_review }} receipt{{ data.counts.needs_review === 1 ? '' : 's' }} need{{ data.counts.needs_review === 1 ? 's' : '' }} your review before their prices count.</span>
        <FontAwesomeIcon icon="chevron-right" />
      </RouterLink>

      <!-- Headline numbers -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-4">
          <p class="text-sm text-slate-500">Potential savings this month</p>
          <p class="mt-1 text-2xl font-bold text-emerald-700 tabular-nums">{{ formatCents(data.potential_savings_this_month_cents) }}</p>
          <p class="text-xs text-slate-500">of {{ formatCents(data.spent_this_month_cents) }} spent</p>
        </div>
        <RouterLink :to="{ name: 'receipts' }" class="card p-4 hover:border-brand-500">
          <p class="text-sm text-slate-500">Receipts</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ data.counts.receipts }}</p>
        </RouterLink>
        <RouterLink :to="{ name: 'products' }" class="card p-4 hover:border-brand-500">
          <p class="text-sm text-slate-500">Products tracked</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ data.counts.products }}</p>
        </RouterLink>
        <RouterLink :to="{ name: 'stores' }" class="card p-4 hover:border-brand-500">
          <p class="text-sm text-slate-500">Stores</p>
          <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ data.counts.stores }}</p>
        </RouterLink>
      </div>

      <!-- Biggest savings -->
      <div class="card overflow-hidden">
        <h2 class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Biggest savings opportunities</h2>
        <p v-if="!data.savings_opportunities.length" class="px-4 py-6 text-sm text-slate-500">
          Nothing to flag: you're already paying the lowest price we know of for what you buy.
        </p>
        <ul class="divide-y divide-slate-100">
          <li v-for="opportunity in data.savings_opportunities" :key="opportunity.product.id">
            <RouterLink :to="{ name: 'products.prices', params: { id: opportunity.product.id } }" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
              <div class="min-w-0 flex-1">
                <div class="font-medium text-slate-900">{{ opportunity.product.name }} <span v-if="opportunity.product.size" class="text-slate-500">{{ opportunity.product.size }}</span></div>
                <div class="text-xs text-slate-500">
                  You paid {{ formatPrice(opportunity.paid_cents, opportunity.unit) }} at {{ opportunity.paid_at.name }} ·
                  {{ formatPrice(opportunity.cheapest_cents, opportunity.unit) }} at {{ opportunity.cheapest_at.name }}
                </div>
              </div>
              <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-sm font-semibold text-emerald-800 tabular-nums">
                Save {{ formatCents(opportunity.saving_cents) }}
              </span>
            </RouterLink>
          </li>
        </ul>
      </div>

      <!-- Price changes -->
      <div class="grid gap-6 lg:grid-cols-2">
        <PriceChangeList title="Got cheaper" direction="down" :changes="data.cheaper" empty="No price drops in the last 30 days." />
        <PriceChangeList title="Got dearer" direction="up" :changes="data.dearer" empty="No price rises in the last 30 days." />
      </div>

      <!-- Recent receipts -->
      <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 class="font-semibold text-slate-900">Recent receipts</h2>
          <RouterLink :to="{ name: 'receipts' }" class="link text-sm">All receipts</RouterLink>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="receipt in data.recent_receipts" :key="receipt.id">
            <RouterLink :to="{ name: 'receipts.details', params: { id: receipt.id } }" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
              <div class="min-w-0 flex-1">
                <div class="truncate font-medium text-slate-900">{{ receipt.store ?? 'Unknown store' }}</div>
                <div class="text-xs text-slate-500">{{ $date(receipt.purchased_at ?? receipt.created_at, { format: 'ddd D MMM YYYY' }) }}</div>
              </div>
              <span class="font-semibold text-slate-900 tabular-nums">{{ formatCents(receipt.total_cents) }}</span>
              <ReceiptStatusBadge :status="receipt.status" />
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PageHeader from '@/components/app/PageHeader.vue'
import PriceChangeList from '@/components/dashboard/PriceChangeList.vue'
import ReceiptStatusBadge from '@/components/receipts/ReceiptStatusBadge.vue'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { http } from '@/utils/http'
import { formatCents, formatPrice } from '@/utils/money'
import type { DashboardData } from '@/types/dashboard'

const { loggedInUser } = useLoggedInUser()
const data = ref<DashboardData | null>(null)

onMounted(async () => {
  const response = await http.get<{ data: DashboardData }>('dashboard')
  data.value = response.data.data
})
</script>
