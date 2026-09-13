import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import InfoWidget from '../../../../src/components/dashboard/widgets/InfoWidget.vue'

vi.mock('@nextcloud/router', () => ({
	imagePath: vi.fn(() => '/apps/byebyemoneylist/img/bbml-logo.png'),
}))

describe('InfoWidget', () => {
	it('renders the logo and the injected version', () => {
		const wrapper = mount(InfoWidget, {
			global: { provide: { appVersion: '1.0.2' } },
		})

		expect(wrapper.find('img').attributes('src')).toBe('/apps/byebyemoneylist/img/bbml-logo.png')
		expect(wrapper.text()).toContain('1.0.2')
	})
})
