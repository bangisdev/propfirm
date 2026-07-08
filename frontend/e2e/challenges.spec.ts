import { test, expect } from '@playwright/test'

test.describe('Challenges & checkout', () => {
  test.use({ storageState: undefined })

  test('an authenticated trader can view challenge pricing tiers', async ({ page }) => {
    // NOTE: assumes a logged-in session has been established (see auth.spec.ts) and
    // the backend has been seeded via `php artisan db:seed` (ChallengeSeeder).
    await page.goto('/dashboard/challenges')

    await expect(page.getByRole('heading', { name: 'Choose your challenge' })).toBeVisible()
    await expect(page.getByText('$5,000').first()).toBeVisible()
  })

  test('selecting a challenge reveals the checkout panel with a coupon field', async ({ page }) => {
    await page.goto('/dashboard/challenges')

    const firstTierCard = page.locator('button').first()
    await firstTierCard.click()

    await expect(page.getByPlaceholder('Coupon code (optional)')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Proceed to payment' })).toBeVisible()
  })
})
