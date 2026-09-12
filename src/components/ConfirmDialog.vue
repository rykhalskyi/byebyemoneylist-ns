<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '../utils/l10n.ts'

const props = withDefaults(defineProps<{
	open: boolean
	title: string
	message: string
	confirmLabel?: string
	busy?: boolean
}>(), {
	confirmLabel: t('Delete'),
	busy: false,
})

const emit = defineEmits<{
	'update:open': [open: boolean]
	confirm: []
}>()

function onCancel() {
	emit('update:open', false)
}

function onConfirm() {
	emit('confirm')
}
</script>

<template>
	<NcDialog
		:name="props.title"
		:open="props.open"
		size="small"
		:noClose="props.busy"
		:closeOnClickOutside="!props.busy"
		@update:open="emit('update:open', $event)">
		<p :class="$style.message">
			{{ props.message }}
		</p>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="props.busy"
				@click="onCancel">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton
				type="button"
				variant="error"
				:disabled="props.busy"
				@click="onConfirm">
				<template #icon>
					<NcLoadingIcon v-if="props.busy" />
				</template>
				{{ props.confirmLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.message {
	margin: 0;
}
</style>
