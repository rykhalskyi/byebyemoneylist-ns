import type { LlmProvider } from '../types.ts'

export interface ProviderOption {
	id: LlmProvider
	label: string
	defaultModel: string
}

export const LLM_PROVIDERS: ProviderOption[] = [
	{
		id: 'deepseek',
		label: 'DeepSeek',
		defaultModel: 'deepseek-v4-flash-vision-exp',
	},
	{
		id: 'siliconflow',
		label: 'SiliconFlow',
		defaultModel: 'Qwen/Qwen3-VL-8B-Instruct',
	},
	{
		id: 'gemini',
		label: 'Google Gemini',
		defaultModel: 'gemini-2.5-flash',
	},
	{
		id: 'openai',
		label: 'OpenAI',
		defaultModel: 'gpt-4o-mini',
	},
	{
		id: 'anthropic',
		label: 'Anthropic',
		defaultModel: 'claude-3-5-haiku',
	},
	{
		id: 'grok',
		label: 'xAI Grok',
		defaultModel: 'grok-2-vision-1212',
	},
]

export function getProviderConfig(provider: LlmProvider): ProviderOption {
	return LLM_PROVIDERS.find((p) => p.id === provider) ?? LLM_PROVIDERS[0]
}
