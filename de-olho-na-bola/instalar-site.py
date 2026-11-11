#!/usr/bin/env python3
"""Copia o tema e o plugin De Olho na Bola para uma instalação WordPress existente."""

from pathlib import Path
import shutil
import tkinter as tk
from tkinter import filedialog, messagebox, ttk


PACKAGE_DIR = Path(__file__).resolve().parent
DEFAULT_PLUGIN = PACKAGE_DIR / "wp-content" / "plugins" / "bola-em-campo-core"
DEFAULT_THEME = PACKAGE_DIR / "wp-content" / "themes" / "bola-em-campo"
PLUGIN_SLUG = "bola-em-campo-core"
THEME_SLUG = "bola-em-campo"


def copy_folder(source, target):
    source = source.resolve()
    target = target.resolve()
    if target == source or source in target.parents:
        raise ValueError("A pasta de origem não pode conter o destino da cópia.")
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copytree(source, target, dirs_exist_ok=True)


class SiteInstaller:
    def __init__(self, window):
        self.window = window
        self.window.title("Instalar De Olho na Bola")
        self.window.minsize(720, 300)

        self.wordpress_dir = tk.StringVar()
        self.plugin_dir = tk.StringVar(value=str(DEFAULT_PLUGIN))
        self.theme_dir = tk.StringVar(value=str(DEFAULT_THEME))

        self._add_path_row(
            0, "Pasta do WordPress (site de destino)", self.wordpress_dir
        )
        self._add_path_row(1, "Pasta do plugin", self.plugin_dir)
        self._add_path_row(2, "Pasta do tema", self.theme_dir)

        ttk.Label(
            window,
            text=(
                "O site WordPress precisa estar criado. Os arquivos existentes "
                "serão mesclados; arquivos com o mesmo nome serão substituídos."
            ),
            wraplength=680,
        ).grid(row=3, column=0, columnspan=3, padx=12, pady=(8, 12), sticky="w")

        ttk.Button(
            window, text="Copiar plugin e tema", command=self.install
        ).grid(row=4, column=0, columnspan=3, padx=12, pady=(0, 12), sticky="e")
        window.columnconfigure(1, weight=1)

    def _add_path_row(self, row, label, variable):
        ttk.Label(self.window, text=label).grid(
            row=row, column=0, padx=(12, 8), pady=7, sticky="w"
        )
        ttk.Entry(self.window, textvariable=variable).grid(
            row=row, column=1, padx=4, pady=7, sticky="ew"
        )
        ttk.Button(
            self.window,
            text="Procurar...",
            command=lambda: self._browse(variable, label),
        ).grid(row=row, column=2, padx=(4, 12), pady=7)

    def _browse(self, variable, title):
        selected = filedialog.askdirectory(
            parent=self.window,
            title=title,
            initialdir=variable.get() or str(Path.home()),
        )
        if selected:
            variable.set(selected)

    @staticmethod
    def _selected_directory(value, label):
        if not value.strip():
            raise ValueError(f"Selecione {label.lower()}.")
        path = Path(value).expanduser().resolve()
        if not path.is_dir():
            raise ValueError(f"A pasta selecionada para {label.lower()} não existe.")
        return path

    def install(self):
        try:
            wordpress = self._selected_directory(
                self.wordpress_dir.get(), "a pasta do WordPress"
            )
            plugin = self._selected_directory(
                self.plugin_dir.get(), "a pasta do plugin"
            )
            theme = self._selected_directory(
                self.theme_dir.get(), "a pasta do tema"
            )

            if not (wordpress / "wp-config.php").is_file() or not (
                wordpress / "wp-content"
            ).is_dir():
                raise ValueError(
                    "A pasta de destino precisa conter wp-config.php e wp-content."
                )
            if not (plugin / "bola-em-campo-core.php").is_file():
                raise ValueError(
                    "A pasta do plugin precisa conter bola-em-campo-core.php."
                )
            style_file = theme / "style.css"
            if not style_file.is_file() or "Theme Name:" not in style_file.read_text(
                encoding="utf-8-sig"
            ):
                raise ValueError(
                    "A pasta do tema precisa conter um style.css válido."
                )

            plugin_target = (
                wordpress / "wp-content" / "plugins" / PLUGIN_SLUG
            ).resolve()
            theme_target = (
                wordpress / "wp-content" / "themes" / THEME_SLUG
            ).resolve()
            for source, target in ((plugin, plugin_target), (theme, theme_target)):
                if target == source or source in target.parents:
                    raise ValueError(
                        "A pasta de origem não pode conter o destino da cópia."
                    )

        except (OSError, ValueError) as error:
            messagebox.showerror("Não foi possível iniciar", str(error), parent=self.window)
            return

        confirmation = (
            f"Destino do plugin:\n{plugin_target}\n\n"
            f"Destino do tema:\n{theme_target}\n\n"
            "Continuar? Arquivos com o mesmo nome serão sobrescritos; "
            "os demais arquivos serão mantidos."
        )
        if not messagebox.askyesno(
            "Confirmar cópia", confirmation, parent=self.window
        ):
            return

        completed = []
        try:
            copy_folder(plugin, plugin_target)
            completed.append("plugin")

            copy_folder(theme, theme_target)
            completed.append("tema")
        except OSError as error:
            copied = ", ".join(completed) if completed else "nenhum arquivo"
            messagebox.showerror(
                "Cópia incompleta",
                f"Erro ao copiar os arquivos: {error}\n\n"
                f"Concluído antes do erro: {copied}. Confira as pastas de destino.",
                parent=self.window,
            )
            return

        messagebox.showinfo(
            "Cópia concluída",
            "Plugin e tema copiados. Agora acesse o painel do WordPress, "
            "ative o plugin, depois o tema, e salve Configurações > Links permanentes.",
            parent=self.window,
        )


def main():
    window = tk.Tk()
    SiteInstaller(window)
    window.mainloop()


if __name__ == "__main__":
    main()
