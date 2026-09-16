import { describe, expect, it } from 'vitest'
import { getProviderConfig, LLM_PROVIDERS } from '../../../src/constants/llmProviders.ts'

describe('llmProviders', () => {
	it('only marks providers with a working scanner as supported', () => {
		const supported = LLM_PROVIDERS.filter((provider) => provider.supported).map((provider) => provider.id)
		expect(supported).toEqual(['deepseek', 'siliconflow'])
	})

	it('falls back to a supported provider for unknown ids', () => {
		expect(getProviderConfig('unexpected' as never).id).toBe('deepseek')
	})
})
