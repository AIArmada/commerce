# Filament Cart

Filament Cart is an admin adapter. Use the cart package's domain actions for condition writes, clearing or destroying carts, and lifecycle transitions. Owner scope must be revalidated server-side for each mutation and export, even when the Filament resource is scoped. Use BuildCartExport and DownloadCart for JSON exports; do not directly update cart snapshot tables. Keep user-facing notifications and Filament presentation in this package.
