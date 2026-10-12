-- dbstock: base de datos INICIAL lista para usar (PostgreSQL 12 o superior).
-- Incluye estructura completa (con unidades, códigos de barra, balanza, padrón de RUC y permisos del Centro de Reportes) y los datos de arranque:
-- permisos, roles (Administrador, Gerente, Cajero, Vendedor, Encargado de stock, Contador), planes y adicionales,
-- sucursal "Casa Central" con depósito y caja, y una categoría. NO incluye usuarios, productos ni clientes.
--
-- Uso:   createdb -U postgres dbStockNew
--        psql -U postgres -d dbStockNew -f dbstock_inicial.sql
--        psql -U postgres -d dbStockNew -f crear_administrador.sql      (crea el usuario administrador)
-- Luego poné los datos de la base en el .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD) y php artisan optimize:clear.
--
--
-- PostgreSQL database dump
--


-- Dumped from database version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria (
    aud_id integer NOT NULL,
    usu_id bigint,
    aud_accion character varying(60) NOT NULL,
    aud_tabla character varying(60),
    aud_registro_id character varying(40),
    aud_detalle text,
    aud_ip character varying(45),
    aud_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: auditoria_aud_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_aud_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_aud_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_aud_id_seq OWNED BY public.auditoria.aud_id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: caja_movimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caja_movimientos (
    mov_id integer NOT NULL,
    ses_id integer NOT NULL,
    mov_tipo character varying(20) NOT NULL,
    mov_monto numeric(12,2) NOT NULL,
    mov_concepto text NOT NULL,
    mov_fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    mov_moneda character varying(5) DEFAULT 'GS'::character varying,
    caj_id_destino integer,
    mov_forma_pago character varying(30),
    vta_id bigint,
    cob_id bigint,
    pag_id bigint,
    CONSTRAINT caja_movimientos_mov_moneda_check CHECK (((mov_moneda)::text = ANY (ARRAY[('GS'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT caja_movimientos_mov_monto_check CHECK ((mov_monto > (0)::numeric)),
    CONSTRAINT caja_movimientos_mov_tipo_check CHECK (((mov_tipo)::text = ANY (ARRAY[('INGRESO'::character varying)::text, ('EGRESO'::character varying)::text])))
);


--
-- Name: caja_movimientos_mov_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caja_movimientos_mov_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caja_movimientos_mov_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caja_movimientos_mov_id_seq OWNED BY public.caja_movimientos.mov_id;


--
-- Name: caja_sesiones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caja_sesiones (
    ses_id integer NOT NULL,
    caj_id integer NOT NULL,
    usu_id integer NOT NULL,
    ses_fecha_apertura timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ses_monto_inicial_gs numeric(12,2) NOT NULL,
    ses_fecha_cierre timestamp without time zone,
    ses_monto_cierre_gs numeric(12,2),
    ses_estado character varying(20) DEFAULT 'ABIERTA'::character varying,
    ses_monto_inicial_usd numeric(15,2) DEFAULT 0,
    ses_monto_inicial_brl numeric(15,2) DEFAULT 0,
    ses_monto_cierre_usd numeric(15,2) DEFAULT 0,
    ses_monto_cierre_brl numeric(15,2) DEFAULT 0,
    ses_monto_esperado_gs numeric(15,2) DEFAULT 0,
    ses_monto_esperado_usd numeric(15,2) DEFAULT 0,
    ses_monto_esperado_brl numeric(15,2) DEFAULT 0,
    ses_esperado_gs numeric(18,2),
    ses_diferencia_gs numeric(18,2),
    ses_esperado_usd numeric(18,2),
    ses_diferencia_usd numeric(18,2),
    ses_esperado_brl numeric(18,2),
    ses_diferencia_brl numeric(18,2),
    ses_observacion_cierre character varying(255),
    CONSTRAINT caja_sesiones_ses_estado_check CHECK (((ses_estado)::text = ANY (ARRAY[('ABIERTA'::character varying)::text, ('CERRADA'::character varying)::text]))),
    CONSTRAINT caja_sesiones_ses_monto_cierre_real_check CHECK ((ses_monto_cierre_gs >= (0)::numeric)),
    CONSTRAINT caja_sesiones_ses_monto_inicial_check CHECK ((ses_monto_inicial_gs >= (0)::numeric))
);


--
-- Name: caja_sesiones_ses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caja_sesiones_ses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caja_sesiones_ses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caja_sesiones_ses_id_seq OWNED BY public.caja_sesiones.ses_id;


--
-- Name: cajas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cajas (
    caj_id integer NOT NULL,
    suc_id integer NOT NULL,
    caj_nombre character varying(50) NOT NULL,
    caj_activa boolean DEFAULT true,
    caj_saldo_gs numeric(15,2) DEFAULT 0,
    caj_saldo_usd numeric(15,2) DEFAULT 0,
    caj_saldo_brl numeric(15,2) DEFAULT 0,
    caj_impresora character varying(100),
    caj_tipo_impresion character varying(30) DEFAULT 'TICKET_SIMPLE'::character varying
);


--
-- Name: cajas_caj_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cajas_caj_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cajas_caj_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cajas_caj_id_seq OWNED BY public.cajas.caj_id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    cat_id integer NOT NULL,
    cat_nombre character varying(100) NOT NULL,
    cat_descripcion text
);


--
-- Name: categorias_cat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_cat_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_cat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_cat_id_seq OWNED BY public.categorias.cat_id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    cli_id integer NOT NULL,
    cli_nombre character varying(100) NOT NULL,
    cli_apellido character varying(100),
    cli_ruc_ci character varying(30) NOT NULL,
    cli_telefono character varying(30),
    cli_direccion text,
    cli_email character varying(100),
    cli_limite_credito numeric(12,2) DEFAULT 0.00,
    cli_es_mayorista boolean DEFAULT false,
    cli_permitir_credito boolean DEFAULT true,
    cli_bloqueado boolean DEFAULT false,
    CONSTRAINT clientes_cli_limite_credito_check CHECK ((cli_limite_credito >= (0)::numeric))
);


--
-- Name: clientes_cli_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_cli_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_cli_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_cli_id_seq OWNED BY public.clientes.cli_id;


--
-- Name: cobranzas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cobranzas (
    cob_id integer NOT NULL,
    suc_id integer NOT NULL,
    cli_id integer NOT NULL,
    usu_id integer NOT NULL,
    ses_id integer NOT NULL,
    cob_fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cob_monto_total numeric(12,2) NOT NULL,
    cob_estado character varying(20) DEFAULT 'ACTIVA'::character varying,
    cob_formapago character varying(30),
    cob_anulada_por bigint,
    cob_anulada_fecha timestamp(0) without time zone,
    cob_motivo_anulacion character varying(255),
    CONSTRAINT cobranzas_cob_estado_check CHECK (((cob_estado)::text = ANY (ARRAY[('ACTIVA'::character varying)::text, ('ANULADA'::character varying)::text]))),
    CONSTRAINT cobranzas_cob_monto_total_check CHECK ((cob_monto_total > (0)::numeric))
);


--
-- Name: cobranzas_cob_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cobranzas_cob_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cobranzas_cob_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cobranzas_cob_id_seq OWNED BY public.cobranzas.cob_id;


--
-- Name: compras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compras (
    com_id integer NOT NULL,
    prov_id bigint NOT NULL,
    usu_id bigint NOT NULL,
    suc_id bigint,
    com_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    com_nro_documento character varying(40),
    com_tipo character varying(10) NOT NULL,
    com_total numeric(18,2) NOT NULL,
    com_estado character varying(12) DEFAULT 'REGISTRADA'::character varying NOT NULL,
    com_observacion character varying(255),
    com_anulada_por bigint,
    com_anulada_fecha timestamp(0) without time zone,
    com_motivo_anulacion character varying(255),
    com_timbrado character varying(20)
);


--
-- Name: compras_com_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compras_com_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compras_com_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_com_id_seq OWNED BY public.compras.com_id;


--
-- Name: configuracion_sistema; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracion_sistema (
    cfg_clave character varying(60) NOT NULL,
    cfg_valor text
);


--
-- Name: cont_asientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cont_asientos (
    asi_id integer NOT NULL,
    asi_numero integer NOT NULL,
    asi_fecha date NOT NULL,
    asi_glosa character varying(255) NOT NULL,
    asi_origen character varying(24) NOT NULL,
    asi_clave character varying(60),
    asi_estado character varying(10) DEFAULT 'VIGENTE'::character varying NOT NULL,
    usu_id bigint,
    asi_revierte_id bigint,
    asi_creado timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: cont_asientos_asi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cont_asientos_asi_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cont_asientos_asi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cont_asientos_asi_id_seq OWNED BY public.cont_asientos.asi_id;


--
-- Name: cont_cuentas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cont_cuentas (
    cue_id integer NOT NULL,
    cue_codigo character varying(20) NOT NULL,
    cue_nombre character varying(120) NOT NULL,
    cue_tipo character varying(12) NOT NULL,
    cue_imputable boolean DEFAULT true NOT NULL,
    cue_activa boolean DEFAULT true NOT NULL,
    cue_clave character varying(40)
);


--
-- Name: cont_cuentas_cue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cont_cuentas_cue_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cont_cuentas_cue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cont_cuentas_cue_id_seq OWNED BY public.cont_cuentas.cue_id;


--
-- Name: cont_lineas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cont_lineas (
    lin_id integer NOT NULL,
    asi_id integer NOT NULL,
    cue_id integer NOT NULL,
    lin_debe numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    lin_haber numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    lin_detalle character varying(160)
);


--
-- Name: cont_lineas_lin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cont_lineas_lin_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cont_lineas_lin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cont_lineas_lin_id_seq OWNED BY public.cont_lineas.lin_id;


--
-- Name: cont_mapeos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cont_mapeos (
    map_id integer NOT NULL,
    map_tipo character varying(20) NOT NULL,
    map_clave character varying(40) NOT NULL,
    cue_id integer NOT NULL
);


--
-- Name: cont_mapeos_map_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cont_mapeos_map_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cont_mapeos_map_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cont_mapeos_map_id_seq OWNED BY public.cont_mapeos.map_id;


--
-- Name: cotizaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cotizaciones (
    cot_id integer NOT NULL,
    cot_fecha date DEFAULT CURRENT_DATE NOT NULL,
    cot_dolar numeric(15,2) NOT NULL,
    cot_real numeric(15,2) NOT NULL,
    cot_activa boolean DEFAULT true,
    creado_en timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: cotizaciones_cot_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cotizaciones_cot_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cotizaciones_cot_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cotizaciones_cot_id_seq OWNED BY public.cotizaciones.cot_id;


--
-- Name: cuentas_cobrar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_cobrar (
    cred_id integer NOT NULL,
    vta_id integer NOT NULL,
    cli_id integer NOT NULL,
    cred_monto_total numeric(12,2) NOT NULL,
    cred_saldo_pendiente numeric(12,2) NOT NULL,
    cred_fecha_emision timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cred_fecha_vencimiento date NOT NULL,
    cred_estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    CONSTRAINT cuentas_cobrar_cred_estado_check CHECK (((cred_estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('PAGADA'::character varying)::text, ('VENCIDA'::character varying)::text, ('ANULADA'::character varying)::text]))),
    CONSTRAINT cuentas_cobrar_cred_monto_total_check CHECK ((cred_monto_total > (0)::numeric)),
    CONSTRAINT cuentas_cobrar_cred_saldo_pendiente_check CHECK ((cred_saldo_pendiente >= (0)::numeric))
);


--
-- Name: cuentas_cobrar_cred_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_cobrar_cred_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_cobrar_cred_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_cobrar_cred_id_seq OWNED BY public.cuentas_cobrar.cred_id;


--
-- Name: cuentas_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_pagar (
    cpa_id integer NOT NULL,
    com_id bigint NOT NULL,
    prov_id bigint NOT NULL,
    cpa_monto_total numeric(18,2) NOT NULL,
    cpa_saldo_pendiente numeric(18,2) NOT NULL,
    cpa_fecha_vencimiento date,
    cpa_estado character varying(12) DEFAULT 'PENDIENTE'::character varying NOT NULL
);


--
-- Name: cuentas_pagar_cpa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_pagar_cpa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_pagar_cpa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_pagar_cpa_id_seq OWNED BY public.cuentas_pagar.cpa_id;


--
-- Name: demo_solicitudes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.demo_solicitudes (
    dem_id integer NOT NULL,
    dem_nombre character varying(120) NOT NULL,
    dem_negocio character varying(150) NOT NULL,
    dem_email character varying(150) NOT NULL,
    dem_telefono character varying(40),
    usu_id bigint,
    dem_ip character varying(45),
    dem_estado character varying(12) DEFAULT 'ACTIVA'::character varying NOT NULL,
    dem_creada timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    dem_vence timestamp(0) without time zone,
    dem_detalle text
);


--
-- Name: demo_solicitudes_dem_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.demo_solicitudes_dem_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: demo_solicitudes_dem_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.demo_solicitudes_dem_id_seq OWNED BY public.demo_solicitudes.dem_id;


--
-- Name: depositos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.depositos (
    dep_id integer NOT NULL,
    suc_id integer NOT NULL,
    dep_nombre character varying(100) NOT NULL,
    dep_descripcion text,
    dep_activo boolean DEFAULT true
);


--
-- Name: depositos_dep_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.depositos_dep_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: depositos_dep_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.depositos_dep_id_seq OWNED BY public.depositos.dep_id;


--
-- Name: detalle_cobranzas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.detalle_cobranzas (
    det_cob_id integer NOT NULL,
    cob_id integer NOT NULL,
    cred_id integer NOT NULL,
    det_monto_pagado numeric(12,2) NOT NULL,
    CONSTRAINT detalle_cobranzas_det_monto_pagado_check CHECK ((det_monto_pagado > (0)::numeric))
);


--
-- Name: detalle_cobranzas_det_cob_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.detalle_cobranzas_det_cob_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_cobranzas_det_cob_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.detalle_cobranzas_det_cob_id_seq OWNED BY public.detalle_cobranzas.det_cob_id;


--
-- Name: detalle_compras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.detalle_compras (
    dco_id integer NOT NULL,
    com_id bigint NOT NULL,
    pro_id bigint NOT NULL,
    dco_cantidad numeric(18,3) NOT NULL,
    dco_costo numeric(18,2) NOT NULL,
    dco_subtotal numeric(18,2) NOT NULL,
    dco_devuelta numeric(18,3) DEFAULT 0 NOT NULL
);


--
-- Name: detalle_compras_dco_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.detalle_compras_dco_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_compras_dco_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.detalle_compras_dco_id_seq OWNED BY public.detalle_compras.dco_id;


--
-- Name: detalle_devoluciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.detalle_devoluciones (
    ddv_id integer NOT NULL,
    dev_id bigint NOT NULL,
    det_vta_id bigint NOT NULL,
    pro_id bigint NOT NULL,
    ddv_cantidad numeric(18,3) NOT NULL,
    ddv_preciounitario numeric(18,2) NOT NULL,
    ddv_subtotal numeric(18,2) NOT NULL
);


--
-- Name: detalle_devoluciones_ddv_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.detalle_devoluciones_ddv_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_devoluciones_ddv_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.detalle_devoluciones_ddv_id_seq OWNED BY public.detalle_devoluciones.ddv_id;


--
-- Name: detalle_presupuestos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.detalle_presupuestos (
    dpr_id integer NOT NULL,
    pre_id bigint NOT NULL,
    pro_id bigint NOT NULL,
    dpr_cantidad numeric(18,3) NOT NULL,
    dpr_precio numeric(18,2) NOT NULL,
    dpr_subtotal numeric(18,2) NOT NULL
);


--
-- Name: detalle_presupuestos_dpr_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.detalle_presupuestos_dpr_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_presupuestos_dpr_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.detalle_presupuestos_dpr_id_seq OWNED BY public.detalle_presupuestos.dpr_id;


--
-- Name: detalle_ventas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.detalle_ventas (
    det_vta_id integer NOT NULL,
    vta_id integer NOT NULL,
    pro_id integer NOT NULL,
    det_cantidad numeric(14,3) NOT NULL,
    det_preciounitario numeric(12,2) NOT NULL,
    det_subtotal numeric(12,2) NOT NULL,
    det_preciocosto numeric(12,2) DEFAULT 0,
    CONSTRAINT detalle_ventas_det_cantidad_check CHECK ((det_cantidad > (0)::numeric)),
    CONSTRAINT detalle_ventas_det_preciounitario_check CHECK ((det_preciounitario >= (0)::numeric)),
    CONSTRAINT detalle_ventas_det_subtotal_check CHECK ((det_subtotal >= (0)::numeric))
);


--
-- Name: detalle_ventas_det_vta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.detalle_ventas_det_vta_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_ventas_det_vta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.detalle_ventas_det_vta_id_seq OWNED BY public.detalle_ventas.det_vta_id;


--
-- Name: devoluciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devoluciones (
    dev_id integer NOT NULL,
    vta_id bigint NOT NULL,
    usu_id bigint,
    ses_id bigint,
    dev_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    dev_total numeric(18,2) NOT NULL,
    dev_motivo character varying(255)
);


--
-- Name: devoluciones_dev_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devoluciones_dev_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devoluciones_dev_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devoluciones_dev_id_seq OWNED BY public.devoluciones.dev_id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: ingresos_egresos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ingresos_egresos (
    ie_id integer NOT NULL,
    ses_id integer NOT NULL,
    ie_tipo character varying(20) NOT NULL,
    ie_monto numeric(15,2) NOT NULL,
    ie_concepto text NOT NULL,
    ie_fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ingresos_egresos_ie_monto_check CHECK ((ie_monto > (0)::numeric)),
    CONSTRAINT ingresos_egresos_ie_tipo_check CHECK (((ie_tipo)::text = ANY (ARRAY[('INGRESO'::character varying)::text, ('EGRESO'::character varying)::text])))
);


--
-- Name: ingresos_egresos_ie_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ingresos_egresos_ie_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ingresos_egresos_ie_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ingresos_egresos_ie_id_seq OWNED BY public.ingresos_egresos.ie_id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: licencia_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.licencia_pagos (
    lpa_id integer NOT NULL,
    plan_id bigint,
    lpa_fecha date NOT NULL,
    lpa_monto numeric(18,2) NOT NULL,
    lpa_forma character varying(30),
    lpa_referencia character varying(120),
    lpa_desde date,
    lpa_hasta date,
    lpa_vence_anterior date,
    lpa_tipo_anterior character varying(12),
    lpa_nota character varying(255),
    lpa_estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    lpa_creado timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    lpa_anulado_fecha timestamp(0) without time zone,
    lpa_motivo_anulacion character varying(255)
);


--
-- Name: licencia_pagos_lpa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.licencia_pagos_lpa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: licencia_pagos_lpa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.licencia_pagos_lpa_id_seq OWNED BY public.licencia_pagos.lpa_id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: padron_ruc; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.padron_ruc (
    pad_ruc character varying(15) NOT NULL,
    pad_dv character varying(2),
    pad_nombre character varying(255) NOT NULL,
    pad_estado character varying(40)
);


--
-- Name: pagos_proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagos_proveedores (
    pag_id integer NOT NULL,
    cpa_id bigint NOT NULL,
    prov_id bigint NOT NULL,
    usu_id bigint NOT NULL,
    ses_id bigint,
    pag_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    pag_monto numeric(18,2) NOT NULL,
    pag_forma_pago character varying(20) NOT NULL,
    pag_referencia character varying(120),
    pag_estado character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
    pag_anulado_por bigint,
    pag_anulado_fecha timestamp(0) without time zone,
    pag_motivo_anulacion character varying(255)
);


--
-- Name: pagos_proveedores_pag_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagos_proveedores_pag_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagos_proveedores_pag_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagos_proveedores_pag_id_seq OWNED BY public.pagos_proveedores.pag_id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permisos (
    perm_id integer NOT NULL,
    perm_codigo character varying(100) NOT NULL,
    perm_descripcion text NOT NULL,
    perm_modulo character varying(50) NOT NULL
);


--
-- Name: permisos_perm_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permisos_perm_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permisos_perm_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permisos_perm_id_seq OWNED BY public.permisos.perm_id;


--
-- Name: plan_adicionales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plan_adicionales (
    ada_id integer NOT NULL,
    ada_nombre character varying(80) NOT NULL,
    ada_precio numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    ada_periodo character varying(20) DEFAULT 'mes'::character varying NOT NULL,
    ada_orden smallint DEFAULT '0'::smallint NOT NULL,
    ada_activo boolean DEFAULT true NOT NULL
);


--
-- Name: plan_adicionales_ada_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plan_adicionales_ada_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plan_adicionales_ada_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plan_adicionales_ada_id_seq OWNED BY public.plan_adicionales.ada_id;


--
-- Name: planes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.planes (
    plan_id integer NOT NULL,
    plan_nombre character varying(80) NOT NULL,
    plan_descripcion character varying(255),
    plan_edicion character varying(10) DEFAULT 'COMPLETA'::character varying NOT NULL,
    plan_modulos text,
    plan_precio numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    plan_meses smallint DEFAULT '1'::smallint NOT NULL,
    plan_max_sucursales integer DEFAULT 0 NOT NULL,
    plan_max_cajas integer DEFAULT 0 NOT NULL,
    plan_max_usuarios integer DEFAULT 0 NOT NULL,
    plan_activo boolean DEFAULT true NOT NULL,
    plan_publico boolean DEFAULT false NOT NULL,
    plan_destacado boolean DEFAULT false NOT NULL,
    plan_orden smallint DEFAULT '0'::smallint NOT NULL,
    plan_etiqueta character varying(30),
    plan_boton character varying(40),
    plan_caracteristicas text
);


--
-- Name: planes_plan_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.planes_plan_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: planes_plan_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.planes_plan_id_seq OWNED BY public.planes.plan_id;


--
-- Name: presupuestos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.presupuestos (
    pre_id integer NOT NULL,
    cli_id bigint,
    pre_cliente_nombre character varying(120),
    usu_id bigint NOT NULL,
    suc_id bigint,
    pre_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    pre_validez_dias smallint NOT NULL,
    pre_fecha_vencimiento date NOT NULL,
    pre_total numeric(18,2) NOT NULL,
    pre_estado character varying(12) DEFAULT 'BORRADOR'::character varying NOT NULL,
    pre_observacion character varying(255),
    pre_condiciones character varying(500),
    pre_aceptado_fecha timestamp(0) without time zone,
    pre_rechazo_motivo character varying(255),
    vta_id bigint,
    pre_facturado_fecha timestamp(0) without time zone
);


--
-- Name: presupuestos_pre_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.presupuestos_pre_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: presupuestos_pre_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.presupuestos_pre_id_seq OWNED BY public.presupuestos.pre_id;


--
-- Name: producto_codigos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.producto_codigos (
    cod_id integer NOT NULL,
    pro_id integer NOT NULL,
    cod_codigo character varying(50) NOT NULL
);


--
-- Name: producto_codigos_cod_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.producto_codigos_cod_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: producto_codigos_cod_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.producto_codigos_cod_id_seq OWNED BY public.producto_codigos.cod_id;


--
-- Name: productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.productos (
    pro_id integer NOT NULL,
    cat_id integer NOT NULL,
    prov_id integer,
    suc_id integer NOT NULL,
    dep_id integer NOT NULL,
    pro_codigo character varying(50) NOT NULL,
    pro_nombre character varying(150) NOT NULL,
    pro_descripcion text,
    pro_preciocosto numeric(12,2) NOT NULL,
    pro_precioventa numeric(12,2) NOT NULL,
    pro_activo boolean DEFAULT true,
    pro_stockactual numeric(14,3) DEFAULT 0 NOT NULL,
    pro_stockminimo numeric(14,3) DEFAULT 5 NOT NULL,
    pro_preciomayorista numeric(12,2) DEFAULT 0 NOT NULL,
    pro_fechavencimiento date,
    pro_imagen character varying(255),
    pro_permite_mayorista boolean DEFAULT true,
    pro_en_promocion boolean DEFAULT false,
    pro_precio_promocional numeric(12,2) DEFAULT 0,
    pro_tipo_iva integer DEFAULT 10,
    pro_unidad character varying(2) DEFAULT 'UN'::character varying NOT NULL,
    pro_plu character varying(10),
    CONSTRAINT productos_pro_preciocosto_check CHECK ((pro_preciocosto >= (0)::numeric)),
    CONSTRAINT productos_pro_precioventa_check CHECK ((pro_precioventa >= (0)::numeric)),
    CONSTRAINT productos_pro_tipo_iva_check CHECK ((pro_tipo_iva = ANY (ARRAY[0, 5, 10]))),
    CONSTRAINT productos_pro_unidad_check CHECK (((pro_unidad)::text = ANY (ARRAY[('UN'::character varying)::text, ('KG'::character varying)::text, ('LT'::character varying)::text])))
);


--
-- Name: productos_pro_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.productos_pro_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: productos_pro_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.productos_pro_id_seq OWNED BY public.productos.pro_id;


--
-- Name: promociones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promociones (
    prom_id integer NOT NULL,
    prom_nombre character varying(150) NOT NULL,
    prom_tipo_descuento character varying(20) NOT NULL,
    prom_valor numeric(12,2) NOT NULL,
    prom_fecha_inicio date DEFAULT CURRENT_DATE NOT NULL,
    prom_fecha_fin date NOT NULL,
    prom_aplica_a character varying(20) NOT NULL,
    cat_id integer,
    pro_id integer,
    prom_activa boolean DEFAULT true,
    CONSTRAINT promociones_prom_aplica_a_check CHECK (((prom_aplica_a)::text = ANY (ARRAY[('PRODUCTO'::character varying)::text, ('CATEGORIA'::character varying)::text]))),
    CONSTRAINT promociones_prom_tipo_descuento_check CHECK (((prom_tipo_descuento)::text = ANY (ARRAY[('PORCENTAJE'::character varying)::text, ('MONTO'::character varying)::text])))
);


--
-- Name: promociones_prom_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.promociones_prom_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: promociones_prom_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.promociones_prom_id_seq OWNED BY public.promociones.prom_id;


--
-- Name: proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedores (
    prov_id integer NOT NULL,
    prov_razonsocial character varying(150) NOT NULL,
    prov_ruc character varying(30),
    prov_telefono character varying(30),
    prov_email character varying(100),
    prov_direccion text
);


--
-- Name: proveedores_prov_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedores_prov_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedores_prov_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedores_prov_id_seq OWNED BY public.proveedores.prov_id;


--
-- Name: rol_permisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rol_permisos (
    rol_id integer NOT NULL,
    perm_id integer NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    rol_id integer NOT NULL,
    rol_nombre character varying(50) NOT NULL,
    rol_descripcion text,
    creado_en timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: roles_rol_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_rol_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_rol_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_rol_id_seq OWNED BY public.roles.rol_id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: stock_movimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_movimientos (
    smo_id integer NOT NULL,
    pro_id bigint NOT NULL,
    smo_tipo character varying(30) NOT NULL,
    smo_cantidad numeric(18,3) NOT NULL,
    smo_stock_resultante numeric(18,3) NOT NULL,
    smo_motivo character varying(255),
    smo_referencia character varying(40),
    usu_id bigint,
    smo_fecha timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: stock_movimientos_smo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_movimientos_smo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_movimientos_smo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_movimientos_smo_id_seq OWNED BY public.stock_movimientos.smo_id;


--
-- Name: sucursales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sucursales (
    suc_id integer NOT NULL,
    suc_nombre character varying(100) NOT NULL,
    suc_direccion text,
    suc_telefono character varying(30),
    suc_activa boolean DEFAULT true,
    suc_actividad_economica text,
    suc_timbrado character varying(20),
    suc_timbrado_inicio date,
    suc_timbrado_fin date,
    suc_est_punto_exp character varying(10) DEFAULT '001-001'::character varying,
    suc_factura_secuencia integer DEFAULT 1
);


--
-- Name: sucursales_suc_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sucursales_suc_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sucursales_suc_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sucursales_suc_id_seq OWNED BY public.sucursales.suc_id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    usu_id integer NOT NULL,
    rol_id integer NOT NULL,
    usu_cedula character varying(20) NOT NULL,
    usu_nombre character varying(100) NOT NULL,
    usu_apellido character varying(100) NOT NULL,
    usu_usuario character varying(50) NOT NULL,
    usu_password character varying(255) NOT NULL,
    usu_activo boolean DEFAULT true,
    creado_en timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usu_email character varying(150)
);


--
-- Name: usuarios_usu_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_usu_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_usu_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_usu_id_seq OWNED BY public.usuarios.usu_id;


--
-- Name: ventas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ventas (
    vta_id integer NOT NULL,
    suc_id integer NOT NULL,
    cli_id integer NOT NULL,
    usu_id integer NOT NULL,
    ses_id integer NOT NULL,
    vta_fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    vta_tipo character varying(20) NOT NULL,
    vta_total numeric(12,2) NOT NULL,
    vta_estado character varying(20) DEFAULT 'CONFIRMADA'::character varying,
    vta_formapago character varying(30) DEFAULT 'EFECTIVO'::character varying,
    vta_timbrado character varying(20),
    vta_nro_factura character varying(20),
    vta_total_exenta numeric(15,2) DEFAULT 0,
    vta_total_iva5 numeric(15,2) DEFAULT 0,
    vta_total_iva10 numeric(15,2) DEFAULT 0,
    vta_cdc character varying(44),
    vta_sifen_estado character varying(20) DEFAULT 'NO_APLICA'::character varying,
    nro_transferencia character varying(100),
    vta_moneda character varying(10) DEFAULT 'GS'::character varying,
    vta_anulada_por bigint,
    vta_anulada_fecha timestamp(0) without time zone,
    vta_motivo_anulacion character varying(255),
    CONSTRAINT ventas_vta_estado_check CHECK (((vta_estado)::text = ANY (ARRAY[('CONFIRMADA'::character varying)::text, ('ANULADA'::character varying)::text]))),
    CONSTRAINT ventas_vta_tipo_check CHECK (((vta_tipo)::text = ANY (ARRAY[('CONTADO'::character varying)::text, ('CREDITO'::character varying)::text]))),
    CONSTRAINT ventas_vta_total_check CHECK ((vta_total >= (0)::numeric))
);


--
-- Name: ventas_vta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ventas_vta_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ventas_vta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ventas_vta_id_seq OWNED BY public.ventas.vta_id;


--
-- Name: auditoria aud_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria ALTER COLUMN aud_id SET DEFAULT nextval('public.auditoria_aud_id_seq'::regclass);


--
-- Name: caja_movimientos mov_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_movimientos ALTER COLUMN mov_id SET DEFAULT nextval('public.caja_movimientos_mov_id_seq'::regclass);


--
-- Name: caja_sesiones ses_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_sesiones ALTER COLUMN ses_id SET DEFAULT nextval('public.caja_sesiones_ses_id_seq'::regclass);


--
-- Name: cajas caj_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas ALTER COLUMN caj_id SET DEFAULT nextval('public.cajas_caj_id_seq'::regclass);


--
-- Name: categorias cat_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN cat_id SET DEFAULT nextval('public.categorias_cat_id_seq'::regclass);


--
-- Name: clientes cli_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN cli_id SET DEFAULT nextval('public.clientes_cli_id_seq'::regclass);


--
-- Name: cobranzas cob_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas ALTER COLUMN cob_id SET DEFAULT nextval('public.cobranzas_cob_id_seq'::regclass);


--
-- Name: compras com_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras ALTER COLUMN com_id SET DEFAULT nextval('public.compras_com_id_seq'::regclass);


--
-- Name: cont_asientos asi_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_asientos ALTER COLUMN asi_id SET DEFAULT nextval('public.cont_asientos_asi_id_seq'::regclass);


--
-- Name: cont_cuentas cue_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_cuentas ALTER COLUMN cue_id SET DEFAULT nextval('public.cont_cuentas_cue_id_seq'::regclass);


--
-- Name: cont_lineas lin_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_lineas ALTER COLUMN lin_id SET DEFAULT nextval('public.cont_lineas_lin_id_seq'::regclass);


--
-- Name: cont_mapeos map_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_mapeos ALTER COLUMN map_id SET DEFAULT nextval('public.cont_mapeos_map_id_seq'::regclass);


--
-- Name: cotizaciones cot_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones ALTER COLUMN cot_id SET DEFAULT nextval('public.cotizaciones_cot_id_seq'::regclass);


--
-- Name: cuentas_cobrar cred_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar ALTER COLUMN cred_id SET DEFAULT nextval('public.cuentas_cobrar_cred_id_seq'::regclass);


--
-- Name: cuentas_pagar cpa_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar ALTER COLUMN cpa_id SET DEFAULT nextval('public.cuentas_pagar_cpa_id_seq'::regclass);


--
-- Name: demo_solicitudes dem_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demo_solicitudes ALTER COLUMN dem_id SET DEFAULT nextval('public.demo_solicitudes_dem_id_seq'::regclass);


--
-- Name: depositos dep_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depositos ALTER COLUMN dep_id SET DEFAULT nextval('public.depositos_dep_id_seq'::regclass);


--
-- Name: detalle_cobranzas det_cob_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_cobranzas ALTER COLUMN det_cob_id SET DEFAULT nextval('public.detalle_cobranzas_det_cob_id_seq'::regclass);


--
-- Name: detalle_compras dco_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_compras ALTER COLUMN dco_id SET DEFAULT nextval('public.detalle_compras_dco_id_seq'::regclass);


--
-- Name: detalle_devoluciones ddv_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_devoluciones ALTER COLUMN ddv_id SET DEFAULT nextval('public.detalle_devoluciones_ddv_id_seq'::regclass);


--
-- Name: detalle_presupuestos dpr_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_presupuestos ALTER COLUMN dpr_id SET DEFAULT nextval('public.detalle_presupuestos_dpr_id_seq'::regclass);


--
-- Name: detalle_ventas det_vta_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_ventas ALTER COLUMN det_vta_id SET DEFAULT nextval('public.detalle_ventas_det_vta_id_seq'::regclass);


--
-- Name: devoluciones dev_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones ALTER COLUMN dev_id SET DEFAULT nextval('public.devoluciones_dev_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: ingresos_egresos ie_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos_egresos ALTER COLUMN ie_id SET DEFAULT nextval('public.ingresos_egresos_ie_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: licencia_pagos lpa_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licencia_pagos ALTER COLUMN lpa_id SET DEFAULT nextval('public.licencia_pagos_lpa_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: pagos_proveedores pag_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_proveedores ALTER COLUMN pag_id SET DEFAULT nextval('public.pagos_proveedores_pag_id_seq'::regclass);


--
-- Name: permisos perm_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos ALTER COLUMN perm_id SET DEFAULT nextval('public.permisos_perm_id_seq'::regclass);


--
-- Name: plan_adicionales ada_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_adicionales ALTER COLUMN ada_id SET DEFAULT nextval('public.plan_adicionales_ada_id_seq'::regclass);


--
-- Name: planes plan_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planes ALTER COLUMN plan_id SET DEFAULT nextval('public.planes_plan_id_seq'::regclass);


--
-- Name: presupuestos pre_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos ALTER COLUMN pre_id SET DEFAULT nextval('public.presupuestos_pre_id_seq'::regclass);


--
-- Name: producto_codigos cod_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_codigos ALTER COLUMN cod_id SET DEFAULT nextval('public.producto_codigos_cod_id_seq'::regclass);


--
-- Name: productos pro_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos ALTER COLUMN pro_id SET DEFAULT nextval('public.productos_pro_id_seq'::regclass);


--
-- Name: promociones prom_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promociones ALTER COLUMN prom_id SET DEFAULT nextval('public.promociones_prom_id_seq'::regclass);


--
-- Name: proveedores prov_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores ALTER COLUMN prov_id SET DEFAULT nextval('public.proveedores_prov_id_seq'::regclass);


--
-- Name: roles rol_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN rol_id SET DEFAULT nextval('public.roles_rol_id_seq'::regclass);


--
-- Name: stock_movimientos smo_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movimientos ALTER COLUMN smo_id SET DEFAULT nextval('public.stock_movimientos_smo_id_seq'::regclass);


--
-- Name: sucursales suc_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales ALTER COLUMN suc_id SET DEFAULT nextval('public.sucursales_suc_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: usuarios usu_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN usu_id SET DEFAULT nextval('public.usuarios_usu_id_seq'::regclass);


--
-- Name: ventas vta_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas ALTER COLUMN vta_id SET DEFAULT nextval('public.ventas_vta_id_seq'::regclass);


--
-- Data for Name: auditoria; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.auditoria (aud_id, usu_id, aud_accion, aud_tabla, aud_registro_id, aud_detalle, aud_ip, aud_fecha) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: caja_movimientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.caja_movimientos (mov_id, ses_id, mov_tipo, mov_monto, mov_concepto, mov_fecha, mov_moneda, caj_id_destino, mov_forma_pago, vta_id, cob_id, pag_id) FROM stdin;
\.


--
-- Data for Name: caja_sesiones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.caja_sesiones (ses_id, caj_id, usu_id, ses_fecha_apertura, ses_monto_inicial_gs, ses_fecha_cierre, ses_monto_cierre_gs, ses_estado, ses_monto_inicial_usd, ses_monto_inicial_brl, ses_monto_cierre_usd, ses_monto_cierre_brl, ses_monto_esperado_gs, ses_monto_esperado_usd, ses_monto_esperado_brl, ses_esperado_gs, ses_diferencia_gs, ses_esperado_usd, ses_diferencia_usd, ses_esperado_brl, ses_diferencia_brl, ses_observacion_cierre) FROM stdin;
\.


--
-- Data for Name: cajas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cajas (caj_id, suc_id, caj_nombre, caj_activa, caj_saldo_gs, caj_saldo_usd, caj_saldo_brl, caj_impresora, caj_tipo_impresion) FROM stdin;
1	1	Caja 1	t	0.00	0.00	0.00	\N	TICKET_SIMPLE
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (cat_id, cat_nombre, cat_descripcion) FROM stdin;
1	General	\N
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (cli_id, cli_nombre, cli_apellido, cli_ruc_ci, cli_telefono, cli_direccion, cli_email, cli_limite_credito, cli_es_mayorista, cli_permitir_credito, cli_bloqueado) FROM stdin;
\.


--
-- Data for Name: cobranzas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cobranzas (cob_id, suc_id, cli_id, usu_id, ses_id, cob_fecha, cob_monto_total, cob_estado, cob_formapago, cob_anulada_por, cob_anulada_fecha, cob_motivo_anulacion) FROM stdin;
\.


--
-- Data for Name: compras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.compras (com_id, prov_id, usu_id, suc_id, com_fecha, com_nro_documento, com_tipo, com_total, com_estado, com_observacion, com_anulada_por, com_anulada_fecha, com_motivo_anulacion, com_timbrado) FROM stdin;
\.


--
-- Data for Name: configuracion_sistema; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configuracion_sistema (cfg_clave, cfg_valor) FROM stdin;
\.


--
-- Data for Name: cont_asientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cont_asientos (asi_id, asi_numero, asi_fecha, asi_glosa, asi_origen, asi_clave, asi_estado, usu_id, asi_revierte_id, asi_creado) FROM stdin;
\.


--
-- Data for Name: cont_cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cont_cuentas (cue_id, cue_codigo, cue_nombre, cue_tipo, cue_imputable, cue_activa, cue_clave) FROM stdin;
\.


--
-- Data for Name: cont_lineas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cont_lineas (lin_id, asi_id, cue_id, lin_debe, lin_haber, lin_detalle) FROM stdin;
\.


--
-- Data for Name: cont_mapeos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cont_mapeos (map_id, map_tipo, map_clave, cue_id) FROM stdin;
\.


--
-- Data for Name: cotizaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cotizaciones (cot_id, cot_fecha, cot_dolar, cot_real, cot_activa, creado_en) FROM stdin;
\.


--
-- Data for Name: cuentas_cobrar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_cobrar (cred_id, vta_id, cli_id, cred_monto_total, cred_saldo_pendiente, cred_fecha_emision, cred_fecha_vencimiento, cred_estado) FROM stdin;
\.


--
-- Data for Name: cuentas_pagar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_pagar (cpa_id, com_id, prov_id, cpa_monto_total, cpa_saldo_pendiente, cpa_fecha_vencimiento, cpa_estado) FROM stdin;
\.


--
-- Data for Name: demo_solicitudes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.demo_solicitudes (dem_id, dem_nombre, dem_negocio, dem_email, dem_telefono, usu_id, dem_ip, dem_estado, dem_creada, dem_vence, dem_detalle) FROM stdin;
\.


--
-- Data for Name: depositos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.depositos (dep_id, suc_id, dep_nombre, dep_descripcion, dep_activo) FROM stdin;
1	1	Depósito principal	\N	t
\.


--
-- Data for Name: detalle_cobranzas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.detalle_cobranzas (det_cob_id, cob_id, cred_id, det_monto_pagado) FROM stdin;
\.


--
-- Data for Name: detalle_compras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.detalle_compras (dco_id, com_id, pro_id, dco_cantidad, dco_costo, dco_subtotal, dco_devuelta) FROM stdin;
\.


--
-- Data for Name: detalle_devoluciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.detalle_devoluciones (ddv_id, dev_id, det_vta_id, pro_id, ddv_cantidad, ddv_preciounitario, ddv_subtotal) FROM stdin;
\.


--
-- Data for Name: detalle_presupuestos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.detalle_presupuestos (dpr_id, pre_id, pro_id, dpr_cantidad, dpr_precio, dpr_subtotal) FROM stdin;
\.


--
-- Data for Name: detalle_ventas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.detalle_ventas (det_vta_id, vta_id, pro_id, det_cantidad, det_preciounitario, det_subtotal, det_preciocosto) FROM stdin;
\.


--
-- Data for Name: devoluciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devoluciones (dev_id, vta_id, usu_id, ses_id, dev_fecha, dev_total, dev_motivo) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: ingresos_egresos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ingresos_egresos (ie_id, ses_id, ie_tipo, ie_monto, ie_concepto, ie_fecha) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: licencia_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.licencia_pagos (lpa_id, plan_id, lpa_fecha, lpa_monto, lpa_forma, lpa_referencia, lpa_desde, lpa_hasta, lpa_vence_anterior, lpa_tipo_anterior, lpa_nota, lpa_estado, lpa_creado, lpa_anulado_fecha, lpa_motivo_anulacion) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_10_09_000001_parte2_caja_ventas_auditoria	1
5	2026_10_10_000001_parte3_cobros_auditoria	1
6	2026_10_11_000001_parte4_stock_movimientos	1
7	2026_10_12_000001_parte5_compras	1
8	2026_10_13_000001_parte6_presupuestos	1
9	2026_10_13_000002_parte6_corregir_presupuestos	1
10	2026_10_14_000001_parte8_panel_vendedor	1
11	2026_10_15_000001_parte9_demo_solicitudes	1
12	2026_10_16_000001_parte10_contabilidad	1
13	2026_10_17_000001_parte11_planes_publicos	1
14	2026_10_19_000001_parte12_roles_base	1
15	2026_10_20_000001_parte13_demo_detalle	1
16	2026_10_21_000001_parte14_unidades_y_codigos	1
17	2026_10_22_000001_parte15_padron_ruc	2
18	2026_10_18_000001_parte11_planes_definitivos	3
19	2026_10_23_000001_parte16_permisos_reportes	4
20	2026_10_24_000001_parte17_permisos_reportes_compras_caja	5
21	2026_10_25_000001_parte18_permisos_reportes_fiscal	6
22	2026_10_26_000001_parte19_ajuste_roles_base	7
\.


--
-- Data for Name: padron_ruc; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.padron_ruc (pad_ruc, pad_dv, pad_nombre, pad_estado) FROM stdin;
\.


--
-- Data for Name: pagos_proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagos_proveedores (pag_id, cpa_id, prov_id, usu_id, ses_id, pag_fecha, pag_monto, pag_forma_pago, pag_referencia, pag_estado, pag_anulado_por, pag_anulado_fecha, pag_motivo_anulacion) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: permisos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permisos (perm_id, perm_codigo, perm_descripcion, perm_modulo) FROM stdin;
1	USUARIOS_GESTIONAR	Crear, editar y desactivar usuarios; administrar roles y permisos	USUARIOS
2	CONFIG_GESTIONAR	Sucursales, cajas, depósitos, cotización y terminal	CONFIGURACION
3	CATALOGO_GESTIONAR	Productos, categorías, proveedores y promociones	CATALOGO
4	CLIENTES_GESTIONAR	Alta, edición y baja de clientes	CLIENTES
5	PADRON_GESTIONAR	Importar, editar y eliminar registros del padrón de RUC (la consulta al cargar clientes no lo necesita)	CLIENTES
6	CLIENTES_CREDITO	Habilitar crédito, fijar límite, marcar mayorista y bloquear clientes	CLIENTES
7	PDV_USAR	Vender en el punto de venta	VENTAS
8	VENTAS_HISTORIAL	Ver y exportar el historial de ventas	VENTAS
9	VENTAS_ANULAR	Anular ventas	VENTAS
10	VENTAS_DEVOLVER	Procesar devoluciones de ventas	VENTAS
11	COBRANZAS_ANULAR	Anular cobros ya registrados (devuelve la deuda al cliente)	COBRANZAS
12	COBRANZAS_REGISTRAR	Cobrar cuentas a crédito	COBRANZAS
13	CAJA_ABRIR_CERRAR	Abrir y cerrar caja	FINANZAS
14	CAJA_TRANSFERIR	Transferir dinero entre cajas	FINANZAS
15	CAJA_INGRESO_EGRESO	Registrar ingresos y egresos extra de caja	FINANZAS
16	FINANZAS_VER	Ver movimientos, cierres e ingresos/egresos	FINANZAS
17	CAJA_OPERAR_AJENA	Cerrar, transferir y registrar movimientos en cajas abiertas por otra persona (responsable)	FINANZAS
18	AUDITORIA_VER	Ver el registro de auditoría (quién hizo qué)	AUDITORIA
19	STOCK_AJUSTAR	Ajustar stock, ingresar mercadería y hacer conteos (queda en el historial)	CATALOGO
20	COMPRAS_REGISTRAR	Registrar compras a proveedores y ver el listado de compras	COMPRAS
21	COMPRAS_ANULAR	Anular compras y devolver mercadería al proveedor	COMPRAS
22	PAGOS_PROVEEDORES	Ver cuentas a pagar y registrar o anular pagos a proveedores	COMPRAS
23	PRESUPUESTOS_GESTIONAR	Crear, editar, aceptar, rechazar y renovar presupuestos (quien vende en caja puede verlos y convertirlos en venta)	VENTAS
24	CONTABILIDAD_VER	Ver libro diario, mayor, balances y libro IVA	CONTABILIDAD
25	CONTABILIDAD_GESTIONAR	Plan de cuentas, mapeos, asientos manuales de ajuste y cierre de períodos	CONTABILIDAD
26	REPORTES_VER	Ver reportes (rentabilidad / curva ABC)	REPORTES
27	REPORTES_VENTAS	Ver reportes de ventas (por período, cajero, forma de pago, categoría, horas pico, anulaciones)	REPORTES
28	REPORTES_STOCK	Ver reportes de stock (valorizado, reposición, vencimientos, kardex, ajustes)	REPORTES
29	REPORTES_CARTERA	Ver reportes de clientes y cartera (cuentas por cobrar, cobranzas, mejores clientes, límites de crédito)	REPORTES
30	REPORTES_COSTOS	Ver costos, utilidades y márgenes dentro de los reportes	REPORTES
31	REPORTES_EXPORTAR	Descargar reportes en Excel y PDF	REPORTES
32	REPORTES_COMPRAS	Ver reportes de compras y proveedores (compras, cuentas por pagar, pagos, evolución de costos)	REPORTES
33	REPORTES_CAJA	Ver reportes de caja y finanzas (cierres, faltantes y sobrantes, flujo de caja)	REPORTES
34	REPORTES_FISCAL	Ver reportes fiscales (libros de IVA, resumen de IVA, facturas por timbrado, numeración, vigencia de timbrados)	REPORTES
\.


--
-- Data for Name: plan_adicionales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plan_adicionales (ada_id, ada_nombre, ada_precio, ada_periodo, ada_orden, ada_activo) FROM stdin;
1	Usuario adicional	25000.00	mes	1	t
2	Caja adicional	45000.00	mes	2	t
3	Sucursal adicional	89000.00	mes	3	t
\.


--
-- Data for Name: planes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.planes (plan_id, plan_nombre, plan_descripcion, plan_edicion, plan_modulos, plan_precio, plan_meses, plan_max_sucursales, plan_max_cajas, plan_max_usuarios, plan_activo, plan_publico, plan_destacado, plan_orden, plan_etiqueta, plan_boton, plan_caracteristicas) FROM stdin;
1	Inicio	Para arrancar o trabajar solo.	BASICA	\N	150000.00	1	1	1	1	t	t	f	1	\N	Probar este plan	Punto de venta, contado y crédito\nCaja con apertura y cierre\nClientes, productos y cobranzas\nPresupuestos con fecha de validez\nInventario y ajustes de stock\n-Compras y proveedores\n-Contabilidad integrada
2	Negocio	Para un local con equipo.	BASICA	\N	220000.00	1	1	2	3	t	t	f	2	\N	Probar este plan	Todo lo del plan Inicio\nDos cajas para atender al mismo tiempo\nHasta 3 usuarios con permisos por rol\n-Compras y proveedores\n-Contabilidad integrada
3	Crecimiento	Para abrir tu segunda sucursal y crecer con control.	COMPLETA	\N	350000.00	1	2	4	6	t	t	t	3	Recomendado	Probar este plan	Todo lo del plan Negocio\nHasta 2 sucursales\nCompras, proveedores y cuentas a pagar\nPromociones y descuentos con fechas\nReportes de rentabilidad y curva ABC\nVentas en guaraníes, dólares y reales\nContabilidad: asientos automáticos, balances y libro IVA
4	Empresa	Para varias sucursales y equipos grandes.	COMPLETA	\N	480000.00	1	3	7	10	t	t	f	4	\N	Probar este plan	Todo lo del plan Crecimiento\nHasta 3 sucursales con control por sucursal\nHasta 7 cajas y 10 usuarios\nTodo el equipo trabajando al mismo tiempo
5	A medida	Para más de 3 sucursales o necesidades propias.	COMPLETA	\N	0.00	1	0	0	0	t	t	f	5	\N	Consultar	Todo lo del plan Empresa\nSucursales, cajas y usuarios según tu necesidad\nSe arma con los adicionales de usuario, caja y sucursal
\.


--
-- Data for Name: presupuestos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.presupuestos (pre_id, cli_id, pre_cliente_nombre, usu_id, suc_id, pre_fecha, pre_validez_dias, pre_fecha_vencimiento, pre_total, pre_estado, pre_observacion, pre_condiciones, pre_aceptado_fecha, pre_rechazo_motivo, vta_id, pre_facturado_fecha) FROM stdin;
\.


--
-- Data for Name: producto_codigos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.producto_codigos (cod_id, pro_id, cod_codigo) FROM stdin;
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (pro_id, cat_id, prov_id, suc_id, dep_id, pro_codigo, pro_nombre, pro_descripcion, pro_preciocosto, pro_precioventa, pro_activo, pro_stockactual, pro_stockminimo, pro_preciomayorista, pro_fechavencimiento, pro_imagen, pro_permite_mayorista, pro_en_promocion, pro_precio_promocional, pro_tipo_iva, pro_unidad, pro_plu) FROM stdin;
\.


--
-- Data for Name: promociones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.promociones (prom_id, prom_nombre, prom_tipo_descuento, prom_valor, prom_fecha_inicio, prom_fecha_fin, prom_aplica_a, cat_id, pro_id, prom_activa) FROM stdin;
\.


--
-- Data for Name: proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedores (prov_id, prov_razonsocial, prov_ruc, prov_telefono, prov_email, prov_direccion) FROM stdin;
\.


--
-- Data for Name: rol_permisos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.rol_permisos (rol_id, perm_id) FROM stdin;
2	3
2	4
2	5
2	6
2	7
2	8
2	9
2	10
2	11
2	12
2	13
2	14
2	15
2	16
2	17
2	18
2	19
2	20
2	21
2	22
2	23
2	26
3	4
3	7
3	8
3	12
3	13
3	15
3	23
4	4
4	23
5	3
5	19
5	20
6	8
6	16
6	18
6	22
6	24
6	25
6	26
2	27
2	28
2	29
2	30
2	31
6	27
6	28
6	29
6	30
6	31
5	28
5	31
2	32
2	33
6	32
6	33
5	32
2	34
6	34
4	7
4	13
4	12
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (rol_id, rol_nombre, rol_descripcion, creado_en) FROM stdin;
1	Administrador	Acceso total al sistema	2026-10-12 13:14:35.568989
2	Gerente	Supervisa todo el negocio: ventas, caja, compras, reportes y auditoría. No administra usuarios ni configuración.	2026-10-12 13:14:35.593841
3	Cajero	Vende y cobra en el punto de venta, abre y cierra su caja y cobra cuentas a crédito.	2026-10-12 13:14:35.631518
4	Vendedor	Atiende clientes y arma presupuestos. No maneja dinero: el cajero los convierte en venta y cobra.	2026-10-12 13:14:35.641157
5	Encargado de stock	Carga productos, recibe mercadería, registra compras y ajusta el stock.	2026-10-12 13:14:35.646323
6	Contador	Contabilidad, libro IVA y reportes. Ve las ventas y la caja, pero no vende ni cobra.	2026-10-12 13:14:35.65223
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: stock_movimientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_movimientos (smo_id, pro_id, smo_tipo, smo_cantidad, smo_stock_resultante, smo_motivo, smo_referencia, usu_id, smo_fecha) FROM stdin;
\.


--
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sucursales (suc_id, suc_nombre, suc_direccion, suc_telefono, suc_activa, suc_actividad_economica, suc_timbrado, suc_timbrado_inicio, suc_timbrado_fin, suc_est_punto_exp, suc_factura_secuencia) FROM stdin;
1	Casa central	\N	\N	t	\N	\N	\N	\N	001-001	1
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuarios (usu_id, rol_id, usu_cedula, usu_nombre, usu_apellido, usu_usuario, usu_password, usu_activo, creado_en, usu_email) FROM stdin;
\.


--
-- Data for Name: ventas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ventas (vta_id, suc_id, cli_id, usu_id, ses_id, vta_fecha, vta_tipo, vta_total, vta_estado, vta_formapago, vta_timbrado, vta_nro_factura, vta_total_exenta, vta_total_iva5, vta_total_iva10, vta_cdc, vta_sifen_estado, nro_transferencia, vta_moneda, vta_anulada_por, vta_anulada_fecha, vta_motivo_anulacion) FROM stdin;
\.


--
-- Name: auditoria_aud_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.auditoria_aud_id_seq', 1, false);


--
-- Name: caja_movimientos_mov_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caja_movimientos_mov_id_seq', 1, false);


--
-- Name: caja_sesiones_ses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caja_sesiones_ses_id_seq', 1, false);


--
-- Name: cajas_caj_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cajas_caj_id_seq', 1, true);


--
-- Name: categorias_cat_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_cat_id_seq', 1, true);


--
-- Name: clientes_cli_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_cli_id_seq', 1, false);


--
-- Name: cobranzas_cob_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cobranzas_cob_id_seq', 1, false);


--
-- Name: compras_com_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compras_com_id_seq', 1, false);


--
-- Name: cont_asientos_asi_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cont_asientos_asi_id_seq', 1, false);


--
-- Name: cont_cuentas_cue_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cont_cuentas_cue_id_seq', 1, false);


--
-- Name: cont_lineas_lin_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cont_lineas_lin_id_seq', 1, false);


--
-- Name: cont_mapeos_map_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cont_mapeos_map_id_seq', 1, false);


--
-- Name: cotizaciones_cot_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cotizaciones_cot_id_seq', 1, false);


--
-- Name: cuentas_cobrar_cred_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_cobrar_cred_id_seq', 1, false);


--
-- Name: cuentas_pagar_cpa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_pagar_cpa_id_seq', 1, false);


--
-- Name: demo_solicitudes_dem_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.demo_solicitudes_dem_id_seq', 1, false);


--
-- Name: depositos_dep_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.depositos_dep_id_seq', 1, true);


--
-- Name: detalle_cobranzas_det_cob_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.detalle_cobranzas_det_cob_id_seq', 1, false);


--
-- Name: detalle_compras_dco_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.detalle_compras_dco_id_seq', 1, false);


--
-- Name: detalle_devoluciones_ddv_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.detalle_devoluciones_ddv_id_seq', 1, false);


--
-- Name: detalle_presupuestos_dpr_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.detalle_presupuestos_dpr_id_seq', 1, false);


--
-- Name: detalle_ventas_det_vta_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.detalle_ventas_det_vta_id_seq', 1, false);


--
-- Name: devoluciones_dev_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.devoluciones_dev_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: ingresos_egresos_ie_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ingresos_egresos_ie_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: licencia_pagos_lpa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.licencia_pagos_lpa_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 22, true);


--
-- Name: pagos_proveedores_pag_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagos_proveedores_pag_id_seq', 1, false);


--
-- Name: permisos_perm_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permisos_perm_id_seq', 34, true);


--
-- Name: plan_adicionales_ada_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plan_adicionales_ada_id_seq', 3, true);


--
-- Name: planes_plan_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.planes_plan_id_seq', 5, true);


--
-- Name: presupuestos_pre_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.presupuestos_pre_id_seq', 1, false);


--
-- Name: producto_codigos_cod_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.producto_codigos_cod_id_seq', 1, false);


--
-- Name: productos_pro_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_pro_id_seq', 1, false);


--
-- Name: promociones_prom_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.promociones_prom_id_seq', 1, false);


--
-- Name: proveedores_prov_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedores_prov_id_seq', 1, false);


--
-- Name: roles_rol_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_rol_id_seq', 6, true);


--
-- Name: stock_movimientos_smo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_movimientos_smo_id_seq', 1, false);


--
-- Name: sucursales_suc_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.sucursales_suc_id_seq', 1, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 1, false);


--
-- Name: usuarios_usu_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuarios_usu_id_seq', 1, true);


--
-- Name: ventas_vta_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ventas_vta_id_seq', 1, false);


--
-- Name: auditoria auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_pkey PRIMARY KEY (aud_id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: caja_movimientos caja_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_movimientos
    ADD CONSTRAINT caja_movimientos_pkey PRIMARY KEY (mov_id);


--
-- Name: caja_sesiones caja_sesiones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_sesiones
    ADD CONSTRAINT caja_sesiones_pkey PRIMARY KEY (ses_id);


--
-- Name: cajas cajas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_pkey PRIMARY KEY (caj_id);


--
-- Name: categorias categorias_cat_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_cat_nombre_key UNIQUE (cat_nombre);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (cat_id);


--
-- Name: clientes clientes_cli_ruc_ci_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_cli_ruc_ci_key UNIQUE (cli_ruc_ci);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (cli_id);


--
-- Name: cobranzas cobranzas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas
    ADD CONSTRAINT cobranzas_pkey PRIMARY KEY (cob_id);


--
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (com_id);


--
-- Name: configuracion_sistema configuracion_sistema_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracion_sistema
    ADD CONSTRAINT configuracion_sistema_pkey PRIMARY KEY (cfg_clave);


--
-- Name: cont_asientos cont_asientos_asi_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_asientos
    ADD CONSTRAINT cont_asientos_asi_clave_unique UNIQUE (asi_clave);


--
-- Name: cont_asientos cont_asientos_asi_numero_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_asientos
    ADD CONSTRAINT cont_asientos_asi_numero_unique UNIQUE (asi_numero);


--
-- Name: cont_asientos cont_asientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_asientos
    ADD CONSTRAINT cont_asientos_pkey PRIMARY KEY (asi_id);


--
-- Name: cont_cuentas cont_cuentas_cue_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_cuentas
    ADD CONSTRAINT cont_cuentas_cue_clave_unique UNIQUE (cue_clave);


--
-- Name: cont_cuentas cont_cuentas_cue_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_cuentas
    ADD CONSTRAINT cont_cuentas_cue_codigo_unique UNIQUE (cue_codigo);


--
-- Name: cont_cuentas cont_cuentas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_cuentas
    ADD CONSTRAINT cont_cuentas_pkey PRIMARY KEY (cue_id);


--
-- Name: cont_lineas cont_lineas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_lineas
    ADD CONSTRAINT cont_lineas_pkey PRIMARY KEY (lin_id);


--
-- Name: cont_mapeos cont_mapeos_map_tipo_map_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_mapeos
    ADD CONSTRAINT cont_mapeos_map_tipo_map_clave_unique UNIQUE (map_tipo, map_clave);


--
-- Name: cont_mapeos cont_mapeos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cont_mapeos
    ADD CONSTRAINT cont_mapeos_pkey PRIMARY KEY (map_id);


--
-- Name: cotizaciones cotizaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_pkey PRIMARY KEY (cot_id);


--
-- Name: cuentas_cobrar cuentas_cobrar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_pkey PRIMARY KEY (cred_id);


--
-- Name: cuentas_pagar cuentas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_pkey PRIMARY KEY (cpa_id);


--
-- Name: demo_solicitudes demo_solicitudes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demo_solicitudes
    ADD CONSTRAINT demo_solicitudes_pkey PRIMARY KEY (dem_id);


--
-- Name: depositos depositos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depositos
    ADD CONSTRAINT depositos_pkey PRIMARY KEY (dep_id);


--
-- Name: detalle_cobranzas detalle_cobranzas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_cobranzas
    ADD CONSTRAINT detalle_cobranzas_pkey PRIMARY KEY (det_cob_id);


--
-- Name: detalle_compras detalle_compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_compras
    ADD CONSTRAINT detalle_compras_pkey PRIMARY KEY (dco_id);


--
-- Name: detalle_devoluciones detalle_devoluciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_devoluciones
    ADD CONSTRAINT detalle_devoluciones_pkey PRIMARY KEY (ddv_id);


--
-- Name: detalle_presupuestos detalle_presupuestos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_presupuestos
    ADD CONSTRAINT detalle_presupuestos_pkey PRIMARY KEY (dpr_id);


--
-- Name: detalle_ventas detalle_ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_ventas
    ADD CONSTRAINT detalle_ventas_pkey PRIMARY KEY (det_vta_id);


--
-- Name: devoluciones devoluciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devoluciones
    ADD CONSTRAINT devoluciones_pkey PRIMARY KEY (dev_id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: ingresos_egresos ingresos_egresos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos_egresos
    ADD CONSTRAINT ingresos_egresos_pkey PRIMARY KEY (ie_id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: licencia_pagos licencia_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licencia_pagos
    ADD CONSTRAINT licencia_pagos_pkey PRIMARY KEY (lpa_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: padron_ruc padron_ruc_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.padron_ruc
    ADD CONSTRAINT padron_ruc_pkey PRIMARY KEY (pad_ruc);


--
-- Name: pagos_proveedores pagos_proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_proveedores
    ADD CONSTRAINT pagos_proveedores_pkey PRIMARY KEY (pag_id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permisos permisos_perm_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_perm_codigo_key UNIQUE (perm_codigo);


--
-- Name: permisos permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_pkey PRIMARY KEY (perm_id);


--
-- Name: plan_adicionales plan_adicionales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_adicionales
    ADD CONSTRAINT plan_adicionales_pkey PRIMARY KEY (ada_id);


--
-- Name: planes planes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planes
    ADD CONSTRAINT planes_pkey PRIMARY KEY (plan_id);


--
-- Name: presupuestos presupuestos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos
    ADD CONSTRAINT presupuestos_pkey PRIMARY KEY (pre_id);


--
-- Name: producto_codigos producto_codigos_cod_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_codigos
    ADD CONSTRAINT producto_codigos_cod_codigo_unique UNIQUE (cod_codigo);


--
-- Name: producto_codigos producto_codigos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_codigos
    ADD CONSTRAINT producto_codigos_pkey PRIMARY KEY (cod_id);


--
-- Name: productos productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pkey PRIMARY KEY (pro_id);


--
-- Name: productos productos_pro_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pro_codigo_key UNIQUE (pro_codigo);


--
-- Name: promociones promociones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promociones
    ADD CONSTRAINT promociones_pkey PRIMARY KEY (prom_id);


--
-- Name: proveedores proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_pkey PRIMARY KEY (prov_id);


--
-- Name: proveedores proveedores_prov_ruc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_prov_ruc_key UNIQUE (prov_ruc);


--
-- Name: rol_permisos rol_permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rol_permisos
    ADD CONSTRAINT rol_permisos_pkey PRIMARY KEY (rol_id, perm_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (rol_id);


--
-- Name: roles roles_rol_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_rol_nombre_key UNIQUE (rol_nombre);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: stock_movimientos stock_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movimientos
    ADD CONSTRAINT stock_movimientos_pkey PRIMARY KEY (smo_id);


--
-- Name: sucursales sucursales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_pkey PRIMARY KEY (suc_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (usu_id);


--
-- Name: usuarios usuarios_usu_cedula_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_usu_cedula_key UNIQUE (usu_cedula);


--
-- Name: usuarios usuarios_usu_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_usu_email_key UNIQUE (usu_email);


--
-- Name: usuarios usuarios_usu_usuario_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_usu_usuario_key UNIQUE (usu_usuario);


--
-- Name: ventas ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_pkey PRIMARY KEY (vta_id);


--
-- Name: ventas ventas_vta_cdc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_vta_cdc_key UNIQUE (vta_cdc);


--
-- Name: auditoria_aud_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_aud_fecha_index ON public.auditoria USING btree (aud_fecha);


--
-- Name: auditoria_aud_tabla_aud_registro_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditoria_aud_tabla_aud_registro_id_index ON public.auditoria USING btree (aud_tabla, aud_registro_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: compras_com_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX compras_com_estado_index ON public.compras USING btree (com_estado);


--
-- Name: compras_com_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX compras_com_fecha_index ON public.compras USING btree (com_fecha);


--
-- Name: compras_prov_id_com_nro_documento_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX compras_prov_id_com_nro_documento_index ON public.compras USING btree (prov_id, com_nro_documento);


--
-- Name: cont_asientos_asi_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cont_asientos_asi_fecha_index ON public.cont_asientos USING btree (asi_fecha);


--
-- Name: cont_asientos_asi_origen_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cont_asientos_asi_origen_index ON public.cont_asientos USING btree (asi_origen);


--
-- Name: cont_lineas_asi_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cont_lineas_asi_id_index ON public.cont_lineas USING btree (asi_id);


--
-- Name: cont_lineas_cue_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cont_lineas_cue_id_index ON public.cont_lineas USING btree (cue_id);


--
-- Name: cuentas_pagar_cpa_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_pagar_cpa_estado_index ON public.cuentas_pagar USING btree (cpa_estado);


--
-- Name: cuentas_pagar_cpa_fecha_vencimiento_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_pagar_cpa_fecha_vencimiento_index ON public.cuentas_pagar USING btree (cpa_fecha_vencimiento);


--
-- Name: cuentas_pagar_prov_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_pagar_prov_id_index ON public.cuentas_pagar USING btree (prov_id);


--
-- Name: demo_solicitudes_dem_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demo_solicitudes_dem_email_index ON public.demo_solicitudes USING btree (dem_email);


--
-- Name: demo_solicitudes_dem_estado_dem_vence_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demo_solicitudes_dem_estado_dem_vence_index ON public.demo_solicitudes USING btree (dem_estado, dem_vence);


--
-- Name: detalle_compras_com_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX detalle_compras_com_id_index ON public.detalle_compras USING btree (com_id);


--
-- Name: detalle_compras_pro_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX detalle_compras_pro_id_index ON public.detalle_compras USING btree (pro_id);


--
-- Name: detalle_devoluciones_dev_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX detalle_devoluciones_dev_id_index ON public.detalle_devoluciones USING btree (dev_id);


--
-- Name: detalle_presupuestos_pre_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX detalle_presupuestos_pre_id_index ON public.detalle_presupuestos USING btree (pre_id);


--
-- Name: detalle_presupuestos_pro_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX detalle_presupuestos_pro_id_index ON public.detalle_presupuestos USING btree (pro_id);


--
-- Name: devoluciones_vta_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX devoluciones_vta_id_index ON public.devoluciones USING btree (vta_id);


--
-- Name: idx_clientes_ruc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_ruc ON public.clientes USING btree (cli_ruc_ci);


--
-- Name: idx_cuentas_cobrar_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_estado ON public.cuentas_cobrar USING btree (cred_estado);


--
-- Name: idx_cuentas_cobrar_vencimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_vencimiento ON public.cuentas_cobrar USING btree (cred_fecha_vencimiento);


--
-- Name: idx_padron_ruc_prefijo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_padron_ruc_prefijo ON public.padron_ruc USING btree (pad_ruc varchar_pattern_ops);


--
-- Name: idx_productos_codigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_codigo ON public.productos USING btree (pro_codigo);


--
-- Name: idx_productos_deposito; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_deposito ON public.productos USING btree (dep_id);


--
-- Name: idx_productos_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_sucursal ON public.productos USING btree (suc_id);


--
-- Name: idx_sucursal_nro_factura; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_sucursal_nro_factura ON public.ventas USING btree (suc_id, vta_nro_factura) WHERE ((vta_nro_factura IS NOT NULL) AND ((vta_estado)::text <> 'ANULADA'::text));


--
-- Name: idx_ventas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_fecha ON public.ventas USING btree (vta_fecha);


--
-- Name: ix_auditoria_accion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_auditoria_accion ON public.auditoria USING btree (aud_accion);


--
-- Name: ix_auditoria_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_auditoria_usuario ON public.auditoria USING btree (usu_id);


--
-- Name: ix_caja_mov_sesion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_caja_mov_sesion ON public.caja_movimientos USING btree (ses_id);


--
-- Name: ix_caja_mov_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_caja_mov_venta ON public.caja_movimientos USING btree (vta_id);


--
-- Name: ix_caja_sesiones_cierre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_caja_sesiones_cierre ON public.caja_sesiones USING btree (ses_fecha_cierre);


--
-- Name: ix_cobranzas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_cobranzas_fecha ON public.cobranzas USING btree (cob_fecha);


--
-- Name: ix_cobranzas_sesion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_cobranzas_sesion ON public.cobranzas USING btree (ses_id);


--
-- Name: ix_cuentas_cobrar_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_cuentas_cobrar_cliente ON public.cuentas_cobrar USING btree (cli_id, cred_estado);


--
-- Name: ix_cuentas_cobrar_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_cuentas_cobrar_venta ON public.cuentas_cobrar USING btree (vta_id);


--
-- Name: ix_detalle_cobranzas_cuenta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_detalle_cobranzas_cuenta ON public.detalle_cobranzas USING btree (cred_id);


--
-- Name: ix_detalle_ventas_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_detalle_ventas_producto ON public.detalle_ventas USING btree (pro_id);


--
-- Name: ix_detalle_ventas_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_detalle_ventas_venta ON public.detalle_ventas USING btree (vta_id);


--
-- Name: ix_sesiones_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_sesiones_usuario ON public.caja_sesiones USING btree (usu_id, ses_estado);


--
-- Name: ix_ventas_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ventas_cliente ON public.ventas USING btree (cli_id);


--
-- Name: ix_ventas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ventas_fecha ON public.ventas USING btree (vta_fecha);


--
-- Name: ix_ventas_sesion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ix_ventas_sesion ON public.ventas USING btree (ses_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: licencia_pagos_lpa_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX licencia_pagos_lpa_estado_index ON public.licencia_pagos USING btree (lpa_estado);


--
-- Name: pagos_proveedores_cpa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pagos_proveedores_cpa_id_index ON public.pagos_proveedores USING btree (cpa_id);


--
-- Name: pagos_proveedores_pag_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pagos_proveedores_pag_fecha_index ON public.pagos_proveedores USING btree (pag_fecha);


--
-- Name: pagos_proveedores_prov_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pagos_proveedores_prov_id_index ON public.pagos_proveedores USING btree (prov_id);


--
-- Name: presupuestos_cli_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX presupuestos_cli_id_index ON public.presupuestos USING btree (cli_id);


--
-- Name: presupuestos_pre_estado_pre_fecha_vencimiento_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX presupuestos_pre_estado_pre_fecha_vencimiento_index ON public.presupuestos USING btree (pre_estado, pre_fecha_vencimiento);


--
-- Name: presupuestos_vta_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX presupuestos_vta_id_index ON public.presupuestos USING btree (vta_id);


--
-- Name: producto_codigos_pro_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX producto_codigos_pro_id_index ON public.producto_codigos USING btree (pro_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: stock_movimientos_pro_id_smo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movimientos_pro_id_smo_id_index ON public.stock_movimientos USING btree (pro_id, smo_id);


--
-- Name: stock_movimientos_smo_fecha_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movimientos_smo_fecha_index ON public.stock_movimientos USING btree (smo_fecha);


--
-- Name: stock_movimientos_smo_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movimientos_smo_tipo_index ON public.stock_movimientos USING btree (smo_tipo);


--
-- Name: uq_caja_sesion_abierta; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_caja_sesion_abierta ON public.caja_sesiones USING btree (caj_id) WHERE ((ses_estado)::text = 'ABIERTA'::text);


--
-- Name: uq_productos_codigo; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_productos_codigo ON public.productos USING btree (pro_codigo) WHERE (pro_codigo IS NOT NULL);


--
-- Name: uq_productos_plu; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_productos_plu ON public.productos USING btree (pro_plu) WHERE (pro_plu IS NOT NULL);


--
-- Name: uq_ventas_factura; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_ventas_factura ON public.ventas USING btree (suc_id, vta_timbrado, vta_nro_factura) WHERE (vta_nro_factura IS NOT NULL);


--
-- Name: caja_movimientos caja_movimientos_caj_id_destino_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_movimientos
    ADD CONSTRAINT caja_movimientos_caj_id_destino_fkey FOREIGN KEY (caj_id_destino) REFERENCES public.cajas(caj_id) ON DELETE SET NULL;


--
-- Name: cajas fk_caja_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT fk_caja_sucursal FOREIGN KEY (suc_id) REFERENCES public.sucursales(suc_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cobranzas fk_cobranza_cliente; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas
    ADD CONSTRAINT fk_cobranza_cliente FOREIGN KEY (cli_id) REFERENCES public.clientes(cli_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cobranzas fk_cobranza_sesion; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas
    ADD CONSTRAINT fk_cobranza_sesion FOREIGN KEY (ses_id) REFERENCES public.caja_sesiones(ses_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cobranzas fk_cobranza_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas
    ADD CONSTRAINT fk_cobranza_sucursal FOREIGN KEY (suc_id) REFERENCES public.sucursales(suc_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cobranzas fk_cobranza_usuario; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cobranzas
    ADD CONSTRAINT fk_cobranza_usuario FOREIGN KEY (usu_id) REFERENCES public.usuarios(usu_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cuentas_cobrar fk_credito_cliente; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT fk_credito_cliente FOREIGN KEY (cli_id) REFERENCES public.clientes(cli_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cuentas_cobrar fk_credito_venta; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT fk_credito_venta FOREIGN KEY (vta_id) REFERENCES public.ventas(vta_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: depositos fk_deposito_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.depositos
    ADD CONSTRAINT fk_deposito_sucursal FOREIGN KEY (suc_id) REFERENCES public.sucursales(suc_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: detalle_cobranzas fk_detcob_cobranza; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_cobranzas
    ADD CONSTRAINT fk_detcob_cobranza FOREIGN KEY (cob_id) REFERENCES public.cobranzas(cob_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: detalle_cobranzas fk_detcob_credito; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_cobranzas
    ADD CONSTRAINT fk_detcob_credito FOREIGN KEY (cred_id) REFERENCES public.cuentas_cobrar(cred_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: detalle_ventas fk_detvta_producto; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_ventas
    ADD CONSTRAINT fk_detvta_producto FOREIGN KEY (pro_id) REFERENCES public.productos(pro_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: detalle_ventas fk_detvta_venta; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.detalle_ventas
    ADD CONSTRAINT fk_detvta_venta FOREIGN KEY (vta_id) REFERENCES public.ventas(vta_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: ingresos_egresos fk_ie_sesion; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos_egresos
    ADD CONSTRAINT fk_ie_sesion FOREIGN KEY (ses_id) REFERENCES public.caja_sesiones(ses_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: caja_movimientos fk_mov_sesion; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_movimientos
    ADD CONSTRAINT fk_mov_sesion FOREIGN KEY (ses_id) REFERENCES public.caja_sesiones(ses_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: productos fk_producto_categoria; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT fk_producto_categoria FOREIGN KEY (cat_id) REFERENCES public.categorias(cat_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: productos fk_producto_deposito; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT fk_producto_deposito FOREIGN KEY (dep_id) REFERENCES public.depositos(dep_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: productos fk_producto_proveedor; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT fk_producto_proveedor FOREIGN KEY (prov_id) REFERENCES public.proveedores(prov_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: productos fk_producto_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT fk_producto_sucursal FOREIGN KEY (suc_id) REFERENCES public.sucursales(suc_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: promociones fk_prom_categoria; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promociones
    ADD CONSTRAINT fk_prom_categoria FOREIGN KEY (cat_id) REFERENCES public.categorias(cat_id) ON DELETE CASCADE;


--
-- Name: promociones fk_prom_producto; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promociones
    ADD CONSTRAINT fk_prom_producto FOREIGN KEY (pro_id) REFERENCES public.productos(pro_id) ON DELETE CASCADE;


--
-- Name: rol_permisos fk_rolperm_permiso; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rol_permisos
    ADD CONSTRAINT fk_rolperm_permiso FOREIGN KEY (perm_id) REFERENCES public.permisos(perm_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rol_permisos fk_rolperm_rol; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rol_permisos
    ADD CONSTRAINT fk_rolperm_rol FOREIGN KEY (rol_id) REFERENCES public.roles(rol_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: caja_sesiones fk_sesion_caja; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_sesiones
    ADD CONSTRAINT fk_sesion_caja FOREIGN KEY (caj_id) REFERENCES public.cajas(caj_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: caja_sesiones fk_sesion_usuario; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caja_sesiones
    ADD CONSTRAINT fk_sesion_usuario FOREIGN KEY (usu_id) REFERENCES public.usuarios(usu_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: usuarios fk_usuario_rol; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES public.roles(rol_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: ventas fk_venta_cliente; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT fk_venta_cliente FOREIGN KEY (cli_id) REFERENCES public.clientes(cli_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: ventas fk_venta_sesion; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT fk_venta_sesion FOREIGN KEY (ses_id) REFERENCES public.caja_sesiones(ses_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: ventas fk_venta_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT fk_venta_sucursal FOREIGN KEY (suc_id) REFERENCES public.sucursales(suc_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: ventas fk_venta_usuario; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT fk_venta_usuario FOREIGN KEY (usu_id) REFERENCES public.usuarios(usu_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: producto_codigos producto_codigos_pro_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_codigos
    ADD CONSTRAINT producto_codigos_pro_id_foreign FOREIGN KEY (pro_id) REFERENCES public.productos(pro_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--


