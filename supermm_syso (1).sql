-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20260303.f1cb7baef2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 15, 2026 at 11:05 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `supermm_syso`
--

-- --------------------------------------------------------

--
-- Table structure for table `actos_inseguros`
--

CREATE TABLE `actos_inseguros` (
  `id` int NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alergias`
--

CREATE TABLE `alergias` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atenciones_medicas`
--

CREATE TABLE `atenciones_medicas` (
  `id` int NOT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `accion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` int DEFAULT NULL,
  `detalles` text COLLATE utf8mb4_unicode_ci,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cursos`
--

CREATE TABLE `cursos` (
  `id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curso_asignaciones`
--

CREATE TABLE `curso_asignaciones` (
  `id` int NOT NULL,
  `curso_id` int NOT NULL,
  `tipo_asignacion` enum('todos','sucursal','departamento','empleado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` int DEFAULT NULL,
  `obligatorio` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `id` int NOT NULL,
  `numero_empleado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departamento_id` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `sucursal_id` int NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleado_alergia`
--

CREATE TABLE `empleado_alergia` (
  `empleado_id` int NOT NULL,
  `alergia_id` int NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleado_curso`
--

CREATE TABLE `empleado_curso` (
  `empleado_id` int NOT NULL,
  `curso_id` int NOT NULL,
  `fecha_realizacion` date NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleado_enfermedad`
--

CREATE TABLE `empleado_enfermedad` (
  `empleado_id` int NOT NULL,
  `enfermedad_id` int NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enfermedades_cronicas`
--

CREATE TABLE `enfermedades_cronicas` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `horas_trabajadas`
--

CREATE TABLE `horas_trabajadas` (
  `id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `año` int NOT NULL,
  `mes` int NOT NULL,
  `horas` decimal(12,2) NOT NULL,
  `registrado_por` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reportes`
--

CREATE TABLE `reportes` (
  `id` int NOT NULL,
  `empleado_id` int NOT NULL,
  `departamento_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `acto_inseguro_id` int DEFAULT NULL,
  `accidente_id` int DEFAULT NULL,
  `evidencia_foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `gravedad` enum('leve','moderado','grave','fatal') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_perdidos` int DEFAULT '0',
  `atencion_medica_id` int DEFAULT NULL,
  `tipo` enum('acto_inseguro','accidente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acto_inseguro',
  `reportado_por` int NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sucursal_id` int NOT NULL,
  `st7` tinyint(1) DEFAULT '0',
  `costo_atencion` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reportes_evidencias`
--

CREATE TABLE `reportes_evidencias` (
  `id` int NOT NULL,
  `reporte_id` int NOT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('imagen','documento') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tipos_accidente`
--

CREATE TABLE `tipos_accidente` (
  `id` int NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre_completo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','usuario','supervisor') COLLATE utf8mb4_unicode_ci DEFAULT 'usuario',
  `sucursal_id` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `debe_cambiar_password` tinyint(1) DEFAULT '0',
  `ultimo_cambio_password` datetime DEFAULT NULL,
  `password_change_required` tinyint(1) DEFAULT '0',
  `password_last_change` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actos_inseguros`
--
ALTER TABLE `actos_inseguros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `descripcion` (`descripcion`);

--
-- Indexes for table `alergias`
--
ALTER TABLE `alergias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `atenciones_medicas`
--
ALTER TABLE `atenciones_medicas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `descripcion` (`descripcion`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `curso_asignaciones`
--
ALTER TABLE `curso_asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_curso_asignaciones_curso` (`curso_id`),
  ADD KEY `idx_curso_asignaciones_tipo_entidad` (`tipo_asignacion`,`entidad_id`);

--
-- Indexes for table `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_empleado` (`numero_empleado`),
  ADD KEY `departamento_id` (`departamento_id`),
  ADD KEY `sucursal_id` (`sucursal_id`),
  ADD KEY `idx_empleados_numero` (`numero_empleado`);
ALTER TABLE `empleados` ADD FULLTEXT KEY `idx_empleados_nombre` (`nombre`);

--
-- Indexes for table `empleado_alergia`
--
ALTER TABLE `empleado_alergia`
  ADD PRIMARY KEY (`empleado_id`,`alergia_id`),
  ADD KEY `idx_empleado_alergia_empleado` (`empleado_id`),
  ADD KEY `idx_empleado_alergia_alergia` (`alergia_id`);

--
-- Indexes for table `empleado_curso`
--
ALTER TABLE `empleado_curso`
  ADD PRIMARY KEY (`empleado_id`,`curso_id`,`fecha_realizacion`),
  ADD KEY `idx_empleado_curso_empleado` (`empleado_id`),
  ADD KEY `idx_empleado_curso_curso` (`curso_id`);

--
-- Indexes for table `empleado_enfermedad`
--
ALTER TABLE `empleado_enfermedad`
  ADD PRIMARY KEY (`empleado_id`,`enfermedad_id`),
  ADD KEY `idx_empleado_enfermedad_empleado` (`empleado_id`),
  ADD KEY `idx_empleado_enfermedad_enfermedad` (`enfermedad_id`);

--
-- Indexes for table `enfermedades_cronicas`
--
ALTER TABLE `enfermedades_cronicas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `horas_trabajadas`
--
ALTER TABLE `horas_trabajadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sucursal_periodo` (`sucursal_id`,`año`,`mes`),
  ADD KEY `registrado_por` (`registrado_por`);

--
-- Indexes for table `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `departamento_id` (`departamento_id`),
  ADD KEY `acto_inseguro_id` (`acto_inseguro_id`),
  ADD KEY `reportado_por` (`reportado_por`),
  ADD KEY `fk_reporte_tipo_accidente` (`accidente_id`),
  ADD KEY `atencion_medica_id` (`atencion_medica_id`),
  ADD KEY `idx_reportes_empleado_fecha` (`empleado_id`,`fecha`),
  ADD KEY `idx_reportes_sucursal_fecha` (`sucursal_id`,`fecha`),
  ADD KEY `idx_reportes_tipo_fecha` (`tipo`,`fecha`),
  ADD KEY `idx_reportes_fecha` (`fecha`);

--
-- Indexes for table `reportes_evidencias`
--
ALTER TABLE `reportes_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reportes_evidencias_reporte` (`reporte_id`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `tipos_accidente`
--
ALTER TABLE `tipos_accidente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `descripcion` (`descripcion`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `sucursal_id` (`sucursal_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actos_inseguros`
--
ALTER TABLE `actos_inseguros`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `alergias`
--
ALTER TABLE `alergias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atenciones_medicas`
--
ALTER TABLE `atenciones_medicas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curso_asignaciones`
--
ALTER TABLE `curso_asignaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enfermedades_cronicas`
--
ALTER TABLE `enfermedades_cronicas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `horas_trabajadas`
--
ALTER TABLE `horas_trabajadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reportes`
--
ALTER TABLE `reportes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reportes_evidencias`
--
ALTER TABLE `reportes_evidencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tipos_accidente`
--
ALTER TABLE `tipos_accidente`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curso_asignaciones`
--
ALTER TABLE `curso_asignaciones`
  ADD CONSTRAINT `curso_asignaciones_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `empleados`
--
ALTER TABLE `empleados`
  ADD CONSTRAINT `empleados_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `empleados_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `empleado_alergia`
--
ALTER TABLE `empleado_alergia`
  ADD CONSTRAINT `empleado_alergia_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `empleado_alergia_ibfk_2` FOREIGN KEY (`alergia_id`) REFERENCES `alergias` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `empleado_curso`
--
ALTER TABLE `empleado_curso`
  ADD CONSTRAINT `empleado_curso_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `empleado_curso_ibfk_2` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `empleado_enfermedad`
--
ALTER TABLE `empleado_enfermedad`
  ADD CONSTRAINT `empleado_enfermedad_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `empleado_enfermedad_ibfk_2` FOREIGN KEY (`enfermedad_id`) REFERENCES `enfermedades_cronicas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horas_trabajadas`
--
ALTER TABLE `horas_trabajadas`
  ADD CONSTRAINT `horas_trabajadas_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  ADD CONSTRAINT `horas_trabajadas_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `reportes`
--
ALTER TABLE `reportes`
  ADD CONSTRAINT `fk_reporte_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_reporte_tipo_accidente` FOREIGN KEY (`accidente_id`) REFERENCES `tipos_accidente` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`),
  ADD CONSTRAINT `reportes_ibfk_2` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`),
  ADD CONSTRAINT `reportes_ibfk_3` FOREIGN KEY (`acto_inseguro_id`) REFERENCES `actos_inseguros` (`id`),
  ADD CONSTRAINT `reportes_ibfk_4` FOREIGN KEY (`reportado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `reportes_ibfk_5` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  ADD CONSTRAINT `reportes_ibfk_6` FOREIGN KEY (`atencion_medica_id`) REFERENCES `atenciones_medicas` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reportes_evidencias`
--
ALTER TABLE `reportes_evidencias`
  ADD CONSTRAINT `reportes_evidencias_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
