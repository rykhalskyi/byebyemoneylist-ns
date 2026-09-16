<script setup lang="ts">
import type { LlmProfile } from '../types.ts'

import { mdiPlus, mdiRobotOutline } from '@mdi/js'
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import LlmProfileCard from '../components/settings/LlmProfileCard.vue'
import LlmProfileDialog from '../components/settings/LlmProfileDialog.vue'
import { activateLlmProfile, fetchLlmProfiles } from '../services/llmApi.ts'
import { t } from '../utils/l10n.ts'

const profiles = ref<LlmProfile[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const actionInProgress = ref(false)

const showDialog = ref(false)
const editingProfile = ref<LlmProfile | undefined>(undefined)

async function loadProfiles() {
	loading.value = true
	error.value = null
	try {
		profiles.value = await fetchLlmProfiles()
	} catch {
		error.value = t('Failed to load LLM profiles.')
	} finally {
		loading.value = false
	}
}

onMounted(loadProfiles)

function onAddProfile() {
	editingProfile.value = undefined
	showDialog.value = true
}

function onEditProfile(profile: LlmProfile) {
	editingProfile.value = profile
	showDialog.value = true
}

async function onSelectProfile(profile: LlmProfile) {
	if (actionInProgress.value) {
		return
	}
	const targetActive = !profile.isActive
	actionInProgress.value = true
	try {
		await activateLlmProfile(profile.id, targetActive)
		profiles.value = profiles.value.map((p) => ({
			...p,
			isActive: p.id === profile.id ? targetActive : false,
		}))
	} catch {
		error.value = t('Failed to update active profile.')
	} finally {
		actionInProgress.value = false
	}
}

function onProfileCreated(profile: LlmProfile) {
	if (profile.isActive) {
		profiles.value = profiles.value.map((p) => ({ ...p, isActive: false }))
	}
	profiles.value.push(profile)
}

function onProfileUpdated(profile: LlmProfile) {
	if (profile.isActive) {
		profiles.value = profiles.value.map((p) => ({ ...p, isActive: false }))
	}
	const index = profiles.value.findIndex((p) => p.id === profile.id)
	if (index !== -1) {
		profiles.value[index] = profile
	}
}

function onProfileDeleted(id: string) {
	profiles.value = profiles.value.filter((p) => p.id !== id)
}
</script>

<template>
	<div :class="$style.settingsView">
		<div :class="$style.container">
			<header :class="$style.header">
				<div>
					<h2 :class="$style.title">
						{{ t('Settings') }}
					</h2>
					<p :class="$style.subtitle">
						{{ t('Manage third-party LLM providers for receipt scanning and OCR.') }}
					</p>
				</div>
			</header>

			<section :class="$style.section">
				<div :class="$style.sectionHeader">
					<div>
						<h3 :class="$style.sectionTitle">
							{{ t('LLM Profiles') }}
						</h3>
						<p :class="$style.sectionDesc">
							{{ t('Configure API keys and default models. Set one profile as active for scanning.') }}
						</p>
					</div>
					<NcButton
						type="button"
						variant="primary"
						@click="onAddProfile">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" :size="20" />
						</template>
						{{ t('Add LLM Profile') }}
					</NcButton>
				</div>

				<div v-if="error" :class="$style.errorMessage">
					{{ error }}
				</div>

				<div v-if="loading" :class="$style.loadingState">
					<NcLoadingIcon :size="48" />
					<p>{{ t('Loading profiles…') }}</p>
				</div>

				<div v-else-if="profiles.length === 0" :class="$style.emptyState">
					<NcEmptyContent
						:name="t('No LLM profiles configured')"
						:description="t('Add a DeepSeek or SiliconFlow API key to enable receipt OCR.')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiRobotOutline" :size="64" />
						</template>
						<template #action>
							<NcButton
								type="button"
								variant="primary"
								@click="onAddProfile">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPlus" :size="20" />
								</template>
								{{ t('Add LLM Profile') }}
							</NcButton>
						</template>
					</NcEmptyContent>
				</div>

				<div v-else :class="$style.profileList">
					<LlmProfileCard
						v-for="profile in profiles"
						:key="profile.id"
						:profile="profile"
						:disabled="actionInProgress"
						@select="onSelectProfile"
						@edit="onEditProfile" />
				</div>
			</section>
		</div>

		<LlmProfileDialog
			v-model:open="showDialog"
			:entity="editingProfile"
			@created="onProfileCreated"
			@updated="onProfileUpdated"
			@deleted="onProfileDeleted" />
	</div>
</template>

<style module>
.settingsView {
	width: 100%;
	display: flex;
	justify-content: center;
}

.container {
	width: 100%;
	max-width: 800px;
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 8px 0;
}

.header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}

.title {
	margin: 0;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.section {
	display: flex;
	flex-direction: column;
	gap: 16px;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	padding: 24px;
}

.sectionHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
}

.sectionTitle {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: var(--color-main-text);
}

.sectionDesc {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.profileList {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.loadingState {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 48px;
	gap: 16px;
	color: var(--color-text-maxcontrast);
}

.emptyState {
	padding: 24px 0;
}

.errorMessage {
	padding: 12px 16px;
	border-radius: var(--border-radius);
	background-color: var(--color-error-background, rgba(233, 59, 59, 0.1));
	color: var(--color-error);
	font-weight: 500;
}
</style>
