<template>
  <div class="flex min-h-dvh flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>
    <MarketingSiteHeader />
    <main id="main" class="flex-1">
      <slot />
    </main>
    <MarketingSiteFooter />
  </div>
</template>
