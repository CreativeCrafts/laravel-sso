# Laravel SSO Admin UI (Optional)

This directory contains **publishable UI scaffolding** for the optional Laravel SSO admin UI.

The package does **not** assume how your application boots Inertia/Vite. Instead, you publish these
files into your application and wire them into your own frontend build.

Publish into your app:

```bash
php artisan vendor:publish --tag=sso-ui

Default publish location:
- resources/vendor/laravel-sso/ui

#### `resources/ui/inertia/Pages/Sso/Home.tsx`
```tsx
import React from 'react';

export default function Home() {
  return (
    <div style={{ padding: 16 }}>
      <h1 style={{ fontSize: 20, fontWeight: 600 }}>Laravel SSO</h1>
      <p style={{ marginTop: 8 }}>
        Admin UI scaffold. Full CRUD screens ship in later milestones.
      </p>
    </div>
  );
}
```
