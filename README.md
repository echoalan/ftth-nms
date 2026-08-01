# 📡 FTTH NMS

## GPON Network Monitoring System

FTTH NMS es una plataforma desarrollada para monitorear redes FTTH/GPON, permitiendo visualizar el estado de ONUs, niveles ópticos y parámetros críticos de la infraestructura.

![FTTH NMS](docs/banner.png)

---

# 🎯 Objetivo

El objetivo del proyecto es centralizar información obtenida desde una red GPON y facilitar las tareas de diagnóstico y mantenimiento.

Permite detectar rápidamente:

- Clientes desconectados
- Niveles ópticos degradados
- Problemas de temperatura
- Distancias ópticas elevadas

---

# ⚙️ Funcionamiento

## 1. Recolección de datos

El módulo Collector se comunica con la OLT y obtiene información de las ONUs:

- Serial Number
- Estado de conexión
- RX Optical Power
- TX Optical Power
- Temperatura
- Distancia

![Collector](docs/collector.png)


## 2. Procesamiento

Los datos obtenidos son normalizados y enviados hacia la API backend para su almacenamiento y consulta.


## 3. Visualización

El frontend consume la API y presenta la información mediante dashboards y mapas interactivos.

---

# 🖥️ Dashboard

![Dashboard](docs/dashboard.png)
![dataClient](docs/dataClient.png)

El panel permite consultar:

- Cantidad de ONUs activas
- Clientes offline
- Niveles ópticos críticos
- Información detallada de cada cliente


---

# 🗺️ Mapa FTTH

![FTTH NMS](docs/banner.png)

La visualización geográfica permite ubicar clientes dentro de la red y detectar zonas problemáticas.


---

# 📊 Parámetros monitoreados

| Parámetro | Descripción |
|---|---|
| RX Power | Nivel óptico recibido por la ONU |
| TX Power | Potencia transmitida |
| Temperature | Temperatura del dispositivo |
| Distance | Distancia aproximada hacia la OLT |
| Status | Estado de conexión |


---

# 🛠️ Tecnologías utilizadas

### Backend
- Laravel
- PHP
- MySQL

### Frontend
- React
- Vite
- Leaflet

### Networking
- GPON
- FTTH
- OLT Monitoring


---