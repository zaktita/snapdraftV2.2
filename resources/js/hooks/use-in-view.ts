import { useEffect, useRef, useState } from 'react';

const DEFAULT_OPTIONS: IntersectionObserverInit = {
    threshold: 0.15,
    rootMargin: '0px 0px -60px 0px',
};

export function useInView<T extends HTMLElement = HTMLDivElement>(
    options = DEFAULT_OPTIONS,
) {
    const ref = useRef<T>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setInView(true);
                observer.unobserve(el);
            }
        }, options);

        observer.observe(el);
        return () => observer.disconnect();
    }, [options]);

    return { ref, inView };
}
