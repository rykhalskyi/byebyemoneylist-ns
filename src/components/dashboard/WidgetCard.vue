<script setup lang="ts">
import { mdiClose, mdiDragVertical } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '../../utils/l10n.ts'

withDefaults(defineProps<{ title: string, icon: string, dragging?: boolean }>(), {
	dragging: false,
})

defineEmits<{ remove: [] }>()
</script>

<template>
	<div :class="[$style.card, { [$style.dragging]: dragging }]" draggable="true">
		<header :class="$style.header">
			<NcIconSvgWrapper :path="icon" :size="20" />
			<h3 :class="$style.title">
				{{ title }}
			</h3>
			<NcIconSvgWrapper :class="$style.handle" :path="mdiDragVertical" :size="20" />
			<NcButton
				type="button"
				variant="tertiary"
				:aria-label="t('Remove widget')"
				@click="$emit('remove')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiClose" :size="20" />
				</template>
			</NcButton>
		</header>
		<div :class="$style.body">
			<slot />
		</div>
	</div>
</template>

<style module>
.card {
	display: flex;
	flex-direction: column;
	gap: 8px;
	box-sizing: border-box;
	min-height: 140px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.card[draggable='true'] {
	cursor: grab;
}

.dragging {
	opacity: 0.5;
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
}

.title {
	flex: 1;
	min-width: 0;
	margin: 0;
	font-size: 1rem;
	font-weight: bold;
}

.handle {
	color: var(--color-text-maxcontrast);
}

.body {
	flex: 1;
}
</style>
