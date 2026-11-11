#!/usr/bin/env python3
"""Exemplo: envia uma pauta e um rascunho ao De Olho na Bola via REST.

Uso:  BEC_URL=http://localhost/wordpress BEC_TOKEN=seu-token python3 enviar_rascunho.py
Só usa a biblioteca padrão. A API apenas cria pautas e rascunhos: nunca publica.
"""
import json
import os
import urllib.error
import urllib.request

BASE = os.environ.get("BEC_URL", "http://localhost/wordpress").rstrip("/")
TOKEN = os.environ["BEC_TOKEN"]


def post(route, payload):
    # ?rest_route= funciona mesmo sem permalinks amigáveis (útil no XAMPP).
    url = f"{BASE}/?rest_route=/bec/v1/{route}"
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", "Authorization": f"Bearer {TOKEN}", "X-BEC-Token": TOKEN},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")


if __name__ == "__main__":
    fontes = [{"nome": "CBF", "url": "https://www.cbf.com.br/"}]

    print(post("pautas", {"title": "Exemplo de pauta sobre o Flamengo", "fontes": fontes, "modelo": "noticia"}))

    # Rascunho com fonte obrigatória; sem "fontes" a API recusa (HTTP 400).
    print(post("rascunhos", {
        "title": "Exemplo de rascunho",
        "content": "<p>[PREENCHER: texto apurado e revisado por uma pessoa]</p>",
        "fontes": fontes,
        "source_id": "exemplo-001",  # evita duplicar se rodar de novo (HTTP 409)
    }))
