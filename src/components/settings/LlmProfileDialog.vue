<script setup lang="ts">
import type { ProviderOption } from '../../constants/llmProviders.ts'
import type { LlmProfile, LlmProfilePayload, LlmProvider } from '../../types.ts'

import { mdiTrashCan } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { LLM_PROVIDERS } from '../../constants/llmProviders.ts'
import { createLlmProfile, deleteLlmProfile, updateLlmProfile } from '../../services/llmApi.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	open: boolean
	entity?: LlmProfile
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [profile: LlmProfile]
	updated: [profile: LlmProfile]
	deleted: [id: string]
}>()

const name = ref('')
const selectedProviderOption = ref<ProviderOption>(LLM_PROVIDERS[0])
const apiKey = ref('')
const model = ref('')
const connectTimeout = ref('30')
const readTimeout = ref('60')
const maxTokens = ref('2048')

const showAdvanced = ref(false)
const submitting = ref(false)
const deleting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const isEditing = computed(() => props.entity !== undefined)

const providerPlaceholder = computed(() => selectedProviderOption.value.defaultModel)

function isProviderSelectable(option: ProviderOption): boolean {
	return option.supported
}

const canSubmit = computed(() => {
	if (submitting.value || deleting.value) {
		return false
	}
	if (name.value.trim() === '') {
		return false
	}
	if (!isEditing.value && apiKey.value.trim() === '') {
		return false
	}
	return true
})

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		error.value = null
		submitting.value = false
		deleting.value = false
		showAdvanced.value = false

		const entity = props.entity
		if (entity) {
			name.value = entity.name
			const foundProvider = LLM_PROVIDERS.find((p) => p.id === entity.provider)
			selectedProviderOption.value = foundProvider ?? LLM_PROVIDERS[0]
			apiKey.value = ''
			model.value = entity.model ?? ''
			connectTimeout.value = String(entity.connectTimeoutSeconds ?? 30)
			readTimeout.value = String(entity.readTimeoutSeconds ?? 60)
			maxTokens.value = String(entity.maxTokens ?? 2048)
		} else {
			name.value = ''
			selectedProviderOption.value = LLM_PROVIDERS[0]
			apiKey.value = ''
			model.value = ''
			connectTimeout.value = '30'
			readTimeout.value = '60'
			maxTokens.value = '2048'
		}

		requestAnimationFrame(() => nameField.value?.focus())
	},
)

function onCancel() {
	emit('update:open', false)
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null

	try {
		const payload: LlmProfilePayload = {
			name: name.value.trim() || selectedProviderOption.value.label,
			provider: selectedProviderOption.value.id as LlmProvider,
			model: model.value.trim() || selectedProviderOption.value.defaultModel,
			connectTimeoutSeconds: parseInt(connectTimeout.value, 10) || 30,
			readTimeoutSeconds: parseInt(readTimeout.value, 10) || 60,
			maxTokens: parseInt(maxTokens.value, 10) || 2048,
		}

		if (apiKey.value.trim() !== '') {
			payload.apiKey = apiKey.value.trim()
		}

		if (props.entity === undefined) {
			const created = await createLlmProfile(payload)
			emit('created', created)
		} else {
			const updated = await updateLlmProfile(props.entity.id, payload)
			emit('updated', updated)
		}
		emit('update:open', false)
	} catch (err: unknown) {
		const serverMessage = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
		error.value = serverMessage
			|| (isEditing.value
				? t('Failed to update LLM profile. Please check your inputs.')
				: t('Failed to create LLM profile. Please check your inputs.'))
	} finally {
		submitting.value = false
	}
}

async function onDelete() {
	if (!props.entity) {
		return
	}
	deleting.value = true
	error.value = null

	try {
		await deleteLlmProfile(props.entity.id)
		emit('deleted', props.entity.id)
		emit('update:open', false)
	} catch (err: unknown) {
		const serverMessage = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
		error.value = serverMessage || t('Failed to delete LLM profile.')
	} finally {
		deleting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? t('Edit LLM profile') : t('Add LLM profile')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				:label="t('Profile Name')"
				:placeholder="selectedProviderOption.label"
				:disabled="submitting || deleting"
				:error="name.trim() === '' && name.length > 0"
				:helperText="t('Give this profile a name to identify it easily.')" />

			<div :class="$style.field">
				<NcSelect
					v-model="selectedProviderOption"
					label="label"
					:inputLabel="t('Provider')"
					:options="LLM_PROVIDERS"
					:selectable="isProviderSelectable"
					:disabled="submitting || deleting"
					:clearable="false" />
				<p :class="$style.hint">
					{{ t('Only DeepSeek and SiliconFlow are supported for receipt scanning right now.') }}
				</p>
			</div>

			<NcTextField
				v-model="apiKey"
				type="password"
				:label="t('API Key')"
				:placeholder="isEditing && props.entity?.apiKeyMasked ? props.entity.apiKeyMasked : t('Paste your API key here')"
				:disabled="submitting || deleting"
				:helperText="isEditing ? t('Leave empty to keep existing encrypted API key.') : t('Your key is encrypted before saving.')" />

			<NcTextField
				v-model="model"
				:label="t('Model (optional)')"
				:placeholder="providerPlaceholder"
				:disabled="submitting || deleting"
				:helperText="t('Leave blank to use default model: {model}', { model: providerPlaceholder })" />

			<div :class="$style.advancedToggle">
				<NcButton
					type="button"
					variant="tertiary"
					@click="showAdvanced = !showAdvanced">
					{{ showAdvanced ? t('Hide advanced options') : t('Show advanced options') }}
				</NcButton>
			</div>

			<div v-if="showAdvanced" :class="$style.advancedSection">
				<NcTextField
					v-model="connectTimeout"
					type="number"
					:label="t('Connect Timeout (seconds)')"
					:disabled="submitting || deleting" />

				<NcTextField
					v-model="readTimeout"
					type="number"
					:label="t('Read Timeout (seconds)')"
					:disabled="submitting || deleting" />

				<NcTextField
					v-model="maxTokens"
					type="number"
					:label="t('Max Tokens')"
					:disabled="submitting || deleting" />
			</div>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				v-if="isEditing"
				type="button"
				variant="error"
				:disabled="submitting || deleting"
				@click="onDelete">
				<template #icon>
					<NcLoadingIcon v-if="deleting" />
					<NcIconSvgWrapper v-else :path="mdiTrashCan" :size="20" />
				</template>
				{{ t('Delete') }}
			</NcButton>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting || deleting"
				@click="onCancel">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ isEditing ? t('Save') : t('Add') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.advancedToggle {
	display: flex;
	justify-content: flex-start;
}

.advancedSection {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 12px;
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
