import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Workbook } from '@fortune-sheet/react';
import '@fortune-sheet/react/dist/index.css';
import { transformExcelToFortune, transformFortuneToExcel } from '@corbe30/fortune-excel';

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function EditorApp({ mount }) {
    const documentUrl = mount.dataset.documentUrl;
    const saveUrl = mount.dataset.saveUrl;
    const returnUrl = mount.dataset.returnUrl;
    const fileName = mount.dataset.fileName || 'classeur.xlsx';

    const sheetRef = useRef(null);
    const dirtyRef = useRef(false);
    const savedRef = useRef(false);

    const [sheets, setSheets] = useState(null);
    const [key, setKey] = useState(0);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        let cancelled = false;

        fetch(documentUrl, { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Impossible de charger le classeur (${response.status}).`);
                }

                const blob = await response.blob();
                const file = new File([blob], fileName, { type: XLSX_MIME });

                await transformExcelToFortune(file, setSheets, setKey, sheetRef);
            })
            .then(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            })
            .catch((loadError) => {
                if (!cancelled) {
                    setError(loadError.message || 'Impossible de charger le classeur.');
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [documentUrl, fileName]);

    useEffect(() => {
        const warnBeforeUnload = (event) => {
            if (!dirtyRef.current || savedRef.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', warnBeforeUnload);

        return () => window.removeEventListener('beforeunload', warnBeforeUnload);
    }, []);

    const handleBack = (event) => {
        if (dirtyRef.current && !savedRef.current && !window.confirm('Quitter sans enregistrer les modifications ?')) {
            event.preventDefault();
        }
    };

    const handleSave = async () => {
        if (saving || !sheetRef.current) {
            return;
        }

        setSaving(true);
        setError('');

        try {
            const exported = await transformFortuneToExcel(sheetRef, 'xlsx', false);

            if (!exported) {
                throw new Error('Le classeur n’a pas pu être exporté.');
            }

            const form = new FormData();
            form.append('document', new Blob([exported], { type: XLSX_MIME }), fileName);

            const response = await fetch(saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: form,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationError = payload.errors
                    ? Object.values(payload.errors).flat()[0]
                    : null;
                throw new Error(validationError || payload.message || `Enregistrement impossible (${response.status}).`);
            }

            savedRef.current = true;
            dirtyRef.current = false;

            window.location.assign(payload.redirect || returnUrl);
        } catch (saveError) {
            setError(saveError.message || 'Enregistrement impossible.');
            setSaving(false);
        }
    };

    return (
        <div className="flex h-screen flex-col bg-slate-100">
            <header className="flex h-16 shrink-0 items-center gap-3 border-b border-slate-700 bg-slate-900 px-3 text-white sm:px-5">
                <a
                    href={returnUrl}
                    onClick={handleBack}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Messagerie
                </a>

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{fileName}</p>
                    <p className="truncate text-[11px] text-slate-400">
                        Édition dans le navigateur · une nouvelle version sera ajoutée au chat
                    </p>
                </div>

                <button
                    type="button"
                    onClick={handleSave}
                    disabled={loading || saving || !sheets}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {saving ? (
                        <>
                            <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                            Enregistrement…
                        </>
                    ) : (
                        <>
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span className="hidden sm:inline">Enregistrer dans le chat</span>
                            <span className="sm:hidden">Enregistrer</span>
                        </>
                    )}
                </button>
            </header>

            {error && (
                <div className="border-b border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700" role="alert">
                    {error}
                </div>
            )}

            <main className="min-h-0 flex-1 overflow-hidden">
                {loading && (
                    <div className="flex h-full items-center justify-center">
                        <div className="flex items-center gap-2 text-sm text-slate-500">
                            <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600" />
                            Ouverture du classeur…
                        </div>
                    </div>
                )}

                {!loading && sheets && (
                    <div className="h-full overflow-hidden bg-white p-2 sm:p-3">
                        <Workbook
                            key={key}
                            ref={sheetRef}
                            data={sheets}
                            onChange={() => {
                                dirtyRef.current = true;
                            }}
                        />
                    </div>
                )}

                {!loading && !sheets && (
                    <div className="flex h-full items-center justify-center px-6 text-center text-sm text-slate-500">
                        Le classeur ne peut pas être affiché. Revenez à la messagerie et vérifiez le fichier.
                    </div>
                )}
            </main>
        </div>
    );
}

const mount = document.getElementById('xlsx-editor-app');

if (mount) {
    createRoot(mount).render(<EditorApp mount={mount} />);
}
