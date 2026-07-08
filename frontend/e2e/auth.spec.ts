import { test, expect } from '@playwright/test'

test.describe('Authentication flow', () => {
  test('a visitor can view the landing page and navigate to register', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { name: /get funded up to/i })).toBeVisible()

    await page.getByRole('link', { name: 'Get Funded' }).click()
    await expect(page).toHaveURL(/\/register/)
  })

  test('registration form shows validation errors for invalid input', async ({ page }) => {
    await page.goto('/register')
    await page.getByRole('button', { name: 'Create account' }).click()

    await expect(page.getByText('Name must be at least 2 characters')).toBeVisible()
    await expect(page.getByText('You must accept the terms')).toBeVisible()
  })

  test('a new trader can register and land on the dashboard', async ({ page }) => {
    const email = `e2e-${Date.now()}@example.com`

    await page.goto('/register')
    await page.getByLabel('Full name').fill('E2E Test Trader')
    await page.getByLabel('Email address').fill(email)
    await page.getByLabel('Password', { exact: true }).fill('StrongPass1!')
    await page.getByLabel('Confirm password').fill('StrongPass1!')
    await page.getByLabel(/I agree to the/).check()
    await page.getByRole('button', { name: 'Create account' }).click()

    await expect(page).toHaveURL(/\/dashboard/)
    await expect(page.getByText(/Welcome back, E2E/)).toBeVisible()
  })

  test('login rejects wrong credentials with an inline error', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email address').fill('nonexistent@example.com')
    await page.getByLabel('Password').fill('WrongPassword1!')
    await page.getByRole('button', { name: 'Sign in' }).click()

    await expect(page.getByRole('alert')).toBeVisible()
  })
})
