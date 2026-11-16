<template>
  <div class="card">
    <h1>Sin permiso</h1>
    <p>Su rol no tiene acceso a esta sección.</p>
    <RouterLink :to="{ name: 'caja' }">Volver a la caja</RouterLink>
  </div>
</template>
