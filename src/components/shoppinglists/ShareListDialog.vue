<script setup lang="ts">
import type { ListShare, ShareMode, ShoppingList } from '../../types.ts'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fetchListShares, revokeListShare, shareList } from '../../services/listsApi.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{ open: boolean, list: ShoppingList, addOnly?: boolean }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	sharesChanged: [hasActiveShares: boolean]
}>()

interface ModeOption {
	id: ShareMode
	label: string
}

const modeOptions: ModeOption[] = [
	{ id: 'readonly', label: t('Read only') },
	{ id: 'readwrite', label: t('Read & write') },
]

const shares = ref<ListShare[]>([])
const user = ref('')
const mode = ref<ModeOption>(modeOptions[0])
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

const canSubmit = computed(() => user.value.trim() !== '' && !submitting.value)
const showSharesList = computed(() => !props.addOnly)

watch(
	() => props.open,
	(open) => {
		if (open) {
			user.value = ''
			mode.value = modeOptions[0]
			error.value = null
			submitting.value = false
			shares.value = []
			if (showSharesList.value) {
				loadShares()
			}
		}
	},
	{ immediate: true },
)

function notifySharesChanged() {
	emit('sharesChanged', shares.value.some((share) => !share.revoked))
}

function serverErrorMessage(err: unknown): string | null {
	const data = (err as { response?: { data?: { message?: string, ocs?: { data?: { message?: string } } } } })?.response?.data
	return data?.ocs?.data?.message ?? data?.message ?? null
}

async function loadShares() {
	loading.value = true
	try {
		shares.value = await fetchListShares(props.list.id)
		notifySharesChanged()
	} catch {
		error.value = t('Failed to load the shares.')
	} finally {
		loading.value = false
	}
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const share = await shareList(props.list.id, user.value.trim(), mode.value.id)
		shares.value = [...shares.value.filter((candidate) => candidate.sharedWith !== share.sharedWith), share]
		notifySharesChanged()
		user.value = ''
		if (props.addOnly) {
			emit('update:open', false)
		}
	} catch (err: unknown) {
		error.value = serverErrorMessage(err) ?? t('Failed to share the list. Please try again.')
	} finally {
		submitting.value = false
	}
}

async function revoke(share: ListShare) {
	submitting.value = true
	error.value = null
	try {
		const updated = await revokeListShare(props.list.id, share.id)
		shares.value = shares.value.map((candidate) => (candidate.id === updated.id ? updated : candidate))
		notifySharesChanged()
	} catch {
		error.value = t('Failed to revoke the share.')
	} finally {
		submitting.value = false
	}
}

function modeOptionFor(share: ListShare): ModeOption {
	return modeOptions.find((option) => option.id === share.mode) ?? modeOptions[0]
}

async function updateMode(share: ListShare, option: ModeOption | null) {
	if (option === null || option.id === share.mode) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const updated = await shareList(props.list.id, share.sharedWith, option.id)
		shares.value = shares.value.map((candidate) => (candidate.id === updated.id ? updated : candidate))
		notifySharesChanged()
	} catch {
		error.value = t('Failed to update the share.')
	} finally {
		submitting.value = false
	}
}

function modeLabel(value: ShareMode): string {
	return value === 'readwrite' ? t('Read & write') : t('Read only')
}

function onCancel() {
	emit('update:open', false)
}
</script>

<template>
	<NcDialog
		:name="t('Share list')"
		:open="props.open"
		size="normal"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<p :class="$style['list-name']">
				{{ props.list.name }}
			</p>

			<div :class="$style.fields">
				<NcTextField
					v-model="user"
					:label="t('User')"
					:placeholder="t('Username')"
					:disabled="submitting" />
				<NcSelect
					v-model="mode"
					label="label"
					:inputLabel="t('Access')"
					:options="modeOptions"
					:clearable="false"
					:disabled="submitting" />
			</div>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>

			<div v-if="showSharesList && shares.length > 0" :class="$style.shares">
				<p :class="$style['shares-title']">
					{{ t('Shared with') }}
				</p>
				<ul :class="$style['share-list']">
					<li v-for="share in shares" :key="share.id" :class="$style['share-row']">
						<span :class="[$style['share-user'], { [$style.muted]: share.revoked }]">{{ share.sharedWith }}</span>
						<NcSelect
							v-if="!share.revoked"
							:modelValue="modeOptionFor(share)"
							label="label"
							:inputLabel="t('Access')"
							:options="modeOptions"
							:clearable="false"
							:disabled="submitting"
							:class="$style['share-mode']"
							@update:modelValue="updateMode(share, $event)" />
						<NcChip v-else :text="modeLabel(share.mode)" noClose />
						<NcButton
							v-if="!share.revoked"
							type="button"
							variant="tertiary"
							:disabled="submitting"
							@click="revoke(share)">
							{{ t('Revoke') }}
						</NcButton>
						<span v-else :class="$style.muted">{{ t('Revoked') }}</span>
					</li>
				</ul>
			</div>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				{{ t('Close') }}
			</NcButton>
			<NcButton
				type="button"
				variant="primary"
				:disabled="!canSubmit"
				@click="onSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ t('Share') }}
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

.list-name {
	font-weight: bold;
	margin: 0;
}

.fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.error {
	color: var(--color-error);
	margin: 0;
}

.shares {
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.shares-title {
	color: var(--color-text-maxcontrast);
	margin: 0 0 8px;
}

.share-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.share-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}

.share-user {
	flex: 1;
	min-width: 0;
}

.share-mode {
	flex: 0 0 auto;
	width: 170px;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
