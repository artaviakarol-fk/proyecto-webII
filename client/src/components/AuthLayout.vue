<script setup lang="ts">
const heroImage = '/img/carrito.webp'
</script>

<template>
  <div class="login">
    <div class="lhero">
      <img :src="heroImage" alt="" />
      <div>
        <h1>TuMiniSúper<br /><em>tu negocio, tu catálogo</em></h1>
        <p>
          Ventas, cobros, fiados y pedidos en línea para minisúper. Cada negocio trabaja con sus propios
          productos, clientes y cuentas.
        </p>
        <ul>
          <li>🏪 Cada negocio aislado de los demás</li>
          <li>📱 SINPE Móvil, tarjeta y efectivo</li>
          <li>🛍️ Tus clientes piden y pagan en línea</li>
        </ul>
      </div>
    </div>
    <div class="lside"><div class="lcard"><slot /></div></div>
  </div>
</template>
