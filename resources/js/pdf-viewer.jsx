import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/build/pdf.mjs';
import PdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';

GlobalWorkerOptions.workerPort = new PdfWorker();

function PdfPage({ pdf, pageNumber, scale }) {
    const wrapperRef = useRef(null);
    const canvasRef = useRef(null);
    const [visible, setVisible] = useState(false);
    const [pageSize, setPageSize] = useState(null);

    useEffect(() => {
        const element = wrapperRef.current;

        if (!element || typeof IntersectionObserver === 'undefined') {
            setVisible(true);

            return undefined;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { rootMargin: '800px 0px' }
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        let cancelled = false;

        pdf.getPage(pageNumber).then((page) => {
            if (cancelled) {
                return;
            }

            const viewport = page.getViewport({ scale: 1 });
            setPageSize({ width: viewport.width, height: viewport.height });
        });

        return () => {
            cancelled = true;
        };
    }, [pdf, pageNumber]);

    useEffect(() => {
        if (!visible || !pageSize) {
            return undefined;
        }

        let cancelled = false;
        let renderTask;

        pdf.getPage(pageNumber).then((page) => {
            if (cancelled) {
                return;
            }

            const viewport = page.getViewport({ scale });
            const canvas = canvasRef.current;

            if (!canvas) {
                return;
            }

            const outputScale = window.devicePixelRatio || 1;
            const context = canvas.getContext('2d');

            canvas.width = Math.floor(viewport.width * outputScale);
            canvas.height = Math.floor(viewport.height * outputScale);
            canvas.style.width = `${viewport.width}px`;
            canvas.style.height = `${viewport.height}px`;

            renderTask = page.render({
                canvas,
                canvasContext: context,
                viewport,
                transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
            });

            renderTask.promise.catch((renderError) => {
                if (!cancelled && renderError?.name !== 'RenderingCancelledException') {
                    console.error('PDF render failed', renderError);
                }
            });
        });

        return () => {
            cancelled = true;
            renderTask?.cancel();
        };
    }, [pdf, pageNumber, scale, visible, pageSize]);

    return (
        <div
            ref={wrapperRef}
            className="mx-auto mb-5 w-full"
            style={{
                maxWidth: pageSize ? `${Math.ceil(pageSize.width * scale)}px` : '800px',
            }}
        >
            <div
                className="relative overflow-hidden rounded-sm bg-white shadow-lg ring-1 ring-black/5"
                style={{
                    aspectRatio: pageSize ? `${pageSize.width} / ${pageSize.height}` : '1 / 1.414',
                }}
            >
                {!visible && (
                    <div className="absolute inset-0 flex items-center justify-center text-xs text-slate-400">
                        Page {pageNumber}
                    </div>
                )}

                <canvas
                    ref={canvasRef}
                    className={`block h-auto w-full ${visible ? '' : 'invisible'}`}
                    aria-label={`Page ${pageNumber}`}
                />
            </div>
        </div>
    );
}

function PdfViewerApp({ mount }) {
    const documentUrl = mount.dataset.documentUrl;
    const downloadUrl = mount.dataset.downloadUrl;
    const returnUrl = mount.dataset.returnUrl;
    const fileName = mount.dataset.fileName || 'document.pdf';

    const viewerRef = useRef(null);
    const [pdf, setPdf] = useState(null);
    const [scale, setScale] = useState(1);
    const [loading, setLoading] = useState(true);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState('');

    const fitToWidth = useCallback(async () => {
        if (!pdf || !viewerRef.current) {
            return;
        }

        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: 1 });
        const available = viewerRef.current.clientWidth - 32;
        const nextScale = Math.min(1.8, Math.max(0.4, available / viewport.width));

        setScale(Number(nextScale.toFixed(2)));
    }, [pdf]);

    useEffect(() => {
        let cancelled = false;
        let loadingTask;

        fetch(documentUrl, { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Impossible de charger le PDF (${response.status}).`);
                }

                const data = await response.arrayBuffer();
                loadingTask = getDocument({ data: new Uint8Array(data) });
                loadingTask.onProgress = ({ loaded, total }) => {
                    if (!cancelled && total) {
                        setProgress(Math.round((loaded / total) * 100));
                    }
                };

                return loadingTask.promise;
            })
            .then((document) => {
                if (!cancelled) {
                    setPdf(document);
                    setLoading(false);
                }
            })
            .catch((loadError) => {
                if (!cancelled) {
                    setError(loadError.message || 'Impossible d’ouvrir ce PDF.');
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
            loadingTask?.destroy();
        };
    }, [documentUrl]);

    useEffect(() => {
        if (pdf) {
            fitToWidth();
        }
    }, [pdf, fitToWidth]);

    const zoom = (delta) => {
        setScale((current) => Math.min(3, Math.max(0.4, Number((current + delta).toFixed(2)))));
    };

    return (
        <div className="flex h-screen flex-col bg-slate-200">
            <header className="flex min-h-16 shrink-0 flex-wrap items-center gap-2 border-b border-slate-700 bg-slate-900 px-3 py-2 text-white sm:flex-nowrap sm:gap-3 sm:px-5">
                <a
                    href={returnUrl}
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
                        {pdf ? `${pdf.numPages} page${pdf.numPages > 1 ? 's' : ''}` : 'Visionneuse PDF'}
                    </p>
                </div>

                <div className="flex items-center gap-1 rounded-lg bg-white/10 p-1">
                    <button
                        type="button"
                        onClick={() => zoom(-0.2)}
                        disabled={!pdf || scale <= 0.4}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-lg leading-none hover:bg-white/15 disabled:opacity-40"
                        aria-label="Réduire le zoom"
                    >
                        −
                    </button>
                    <span className="w-12 text-center text-xs font-medium tabular-nums">
                        {Math.round(scale * 100)}%
                    </span>
                    <button
                        type="button"
                        onClick={() => zoom(0.2)}
                        disabled={!pdf || scale >= 3}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-lg leading-none hover:bg-white/15 disabled:opacity-40"
                        aria-label="Agrandir le zoom"
                    >
                        +
                    </button>
                    <button
                        type="button"
                        onClick={fitToWidth}
                        disabled={!pdf}
                        className="ml-1 rounded-md px-2 py-1.5 text-xs font-medium text-slate-200 hover:bg-white/15 disabled:opacity-40"
                    >
                        Ajuster
                    </button>
                </div>

                <a
                    href={downloadUrl}
                    download={fileName}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-400"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span className="hidden sm:inline">Télécharger</span>
                </a>
            </header>

            {error && (
                <div className="border-b border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700" role="alert">
                    {error}
                </div>
            )}

            <main ref={viewerRef} className="min-h-0 flex-1 overflow-auto px-2 py-4 sm:px-6">
                {loading && (
                    <div className="flex h-full items-center justify-center">
                        <div className="flex flex-col items-center gap-2 text-sm text-slate-500">
                            <span className="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
                            <span>Ouverture du PDF… {progress > 0 ? `${progress}%` : ''}</span>
                        </div>
                    </div>
                )}

                {!loading && pdf && pdf.numPages > 0 && (
                    <div>
                        {Array.from({ length: pdf.numPages }, (_, index) => (
                            <PdfPage key={index + 1} pdf={pdf} pageNumber={index + 1} scale={scale} />
                        ))}
                    </div>
                )}

                {!loading && !pdf && (
                    <div className="flex h-full items-center justify-center text-sm text-slate-500">
                        Le PDF ne peut pas être affiché.
                    </div>
                )}
            </main>
        </div>
    );
}

const mount = document.getElementById('pdf-viewer-app');

if (mount) {
    createRoot(mount).render(<PdfViewerApp mount={mount} />);
}
