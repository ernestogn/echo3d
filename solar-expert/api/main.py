from fastapi import FastAPI
from pydantic import BaseModel, Field
from pathlib import Path
from fastapi.middleware.cors import CORSMiddleware
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "core"))
from engine import OffGridDesign
from reportes import generar_html

app = FastAPI(
    title="Solar Expert API",
    description="API para dimensionamiento de sistemas solares off-grid",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class DiseñoRequest(BaseModel):
    ubicacion: str = Field(..., description="Provincia o ciudad, ej: Entre Ríos")
    consumo_mensual_kwh: float = Field(..., ge=1, description="Consumo mensual estimado (kWh/mes)")
    consumo_anual_promedio_kwh: float | None = Field(None, ge=1, description="Promedio anual (si difiere del mensual)")
    potencia_demandada_w: float = Field(0, ge=0, description="Potencia pico demandada (W)")
    factor_uso: float = Field(0.6, ge=0.1, le=1.0, description="Factor de simultaneidad 0-1")
    dias_autonomia: int = Field(1, ge=1, le=14, description="Días de autonomía deseados")
    tipo_bateria: str = Field("LiFePO4", description="LiFePO4 | Plomo-ácido")
    tension_sistema: int | None = Field(None, description="12 | 24 | 48 (dejar None para auto)")
    panel_id: str | None = Field(None, description="ID de panel del catálogo (opcional)")
    lat: float | None = Field(None, description="Latitud GPS (opcional)")
    lon: float | None = Field(None, description="Longitud GPS (opcional)")
    modo_coordenadas: str = Field("provincia", description="provincia | coordenadas | hibrido")


@app.get("/health")
def health():
    return {"ok": True, "service": "solar-expert-api"}


@app.post("/calcular")
def calcular(req: DiseñoRequest):
    diseño = OffGridDesign(
        ubicacion=req.ubicacion,
        consumo_mensual_kwh=req.consumo_mensual_kwh,
        consumo_anual_promedio_kwh=req.consumo_anual_promedio_kwh,
        potencia_demandada_w=req.potencia_demandada_w,
        factor_uso=req.factor_uso,
        dias_autonomia=req.dias_autonomia,
        tipo_bateria=req.tipo_bateria,
        tension_sistema=req.tension_sistema,
        panel_id=req.panel_id,
        lat=req.lat,
        lon=req.lon,
        modo_coordenadas=req.modo_coordenadas,
    )
    return {"ok": True, "resultado": diseño.to_dict()}


@app.post("/informe/html")
def informe_html(req: DiseñoRequest):
    diseño = OffGridDesign(
        ubicacion=req.ubicacion,
        consumo_mensual_kwh=req.consumo_mensual_kwh,
        consumo_anual_promedio_kwh=req.consumo_anual_promedio_kwh,
        potencia_demandada_w=req.potencia_demandada_w,
        factor_uso=req.factor_uso,
        dias_autonomia=req.dias_autonomia,
        tipo_bateria=req.tipo_bateria,
        tension_sistema=req.tension_sistema,
        panel_id=req.panel_id,
        lat=req.lat,
        lon=req.lon,
        modo_coordenadas=req.modo_coordenadas,
    )
    html = generar_html(diseño.to_dict())
    from fastapi.responses import HTMLResponse
    return HTMLResponse(content=html)
