<script setup lang="ts">
import { mdiTagMultiple } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineProps<{
	color?: string | null
	emoji?: string | null
}>()
</script>

<template>
	<span
		:class="$style.bubble"
		:style="color ? { backgroundColor: color } : {}">
		<span v-if="emoji">{{ emoji }}</span>
		<NcIconSvgWrapper v-else :path="mdiTagMultiple" :size="16" />
	</span>
</template>

<style module>
.bubble {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	font-size: 16px;
	line-height: 1;
	background-color: var(--color-background-darker);
	color: var(--color-main-background);
}
</style>
