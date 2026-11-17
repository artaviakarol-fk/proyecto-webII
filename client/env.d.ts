/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Id of the negocio where new clientes register when the link has no ?negocio=. */
  readonly VITE_NEGOCIO_ID?: string
}
