<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '../utils/l10n.ts'

withDefaults(defineProps<{
	open: boolean
	busy?: boolean
	error?: string | null
}>(), {
	busy: false,
	error: null,
})

const emit = defineEmits<{
	'update:open': [open: boolean]
	confirm: []
}>()
</script>

<template>
	<NcDialog
		:name="t('Create default categories?')"
		:open="open"
		size="small"
		:noClose="busy"
		:closeOnClickOutside="!busy"
		@update:open="emit('update:open', $event)">
		<p :class="$style.message">
			{{ t('You don\'t have any categories yet. Do you want to create a default set?') }}
		</p>
		<p v-if="error" :class="$style.error">
			{{ error }}
		</p>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="busy"
				@click="emit('update:open', false)">
				{{ t('Not now') }}
			</NcButton>
			<NcButton
				type="button"
				variant="primary"
				:disabled="busy"
				@click="emit('confirm')">
				<template #icon>
					<NcLoadingIcon v-if="busy" />
				</template>
				{{ t('Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.message {
	margin: 0 0 8px;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
