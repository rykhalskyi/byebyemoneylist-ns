<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { fetchSpending } from '../../../services/dashboardApi.ts'
import { formatTotal } from '../../../utils/format.ts'
import { t } from '../../../utils/l10n.ts'

const props = withDefaults(defineProps<{
	from: string
	to: string
	categoryId?: string | null
}>(), {
	categoryId: null,
})

const total = ref<number | null>(null)
const loading = ref(true)
const failed = ref(false)

async function load(): Promise<void> {
	loading.value = true
	failed.value = false
	try {
		const spending = await fetchSpending({ from: props.from, to: props.to, categoryId: props.categoryId })
		total.value = spending.total
	} catch {
		failed.value = true
	} finally {
		loading.value = false
	}
}

onMounted(load)
</script>

<template>
	<div :class="$style.value">
		<NcLoadingIcon v-if="loading" />
		<template v-else-if="failed">
			<p :class="$style.error">
				{{ t('Failed to load the total.') }}
			</p>
			<NcButton type="button" variant="tertiary" @click="load">
				{{ t('Try again') }}
			</NcButton>
		</template>
		<p v-else :class="$style.amount">
			{{ formatTotal(total ?? 0) }}
		</p>
	</div>
</template>

<style module>
.value {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
}

.amount {
	margin: 0;
	font-size: 1.75rem;
	font-weight: bold;
	font-variant-numeric: tabular-nums;
}

.error {
	margin: 0;
	color: var(--color-error);
}
</style>
