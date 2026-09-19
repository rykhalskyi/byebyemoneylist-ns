<script setup lang="ts">
import { mdiChevronLeft, mdiChevronRight } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '../../utils/l10n.ts'

defineProps<{ label: string }>()

const emit = defineEmits<{ previous: [], next: [] }>()
</script>

<template>
	<div :class="$style.picker">
		<NcButton
			type="button"
			variant="tertiary-no-background"
			:aria-label="t('Previous month')"
			@click="emit('previous')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiChevronLeft" :size="20" />
			</template>
		</NcButton>
		<span :class="$style.label">{{ label }}</span>
		<NcButton
			type="button"
			variant="tertiary-no-background"
			:aria-label="t('Next month')"
			@click="emit('next')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiChevronRight" :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<style module>
.picker {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
}

.label {
	font-size: 1.1rem;
	font-weight: 600;
	min-width: 160px;
	text-align: center;
}
</style>
