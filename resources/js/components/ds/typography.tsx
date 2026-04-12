import { cn } from '@/lib/utils';
import { PropsWithChildren } from 'react';

export function H1({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <h1 className={cn('ds-heading-1', className)}>{children}</h1>;
}

export function Display1({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <h1 className={cn('ds-display-1', className)}>{children}</h1>;
}

export function H2({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <h2 className={cn('ds-heading-2', className)}>{children}</h2>;
}

export function Subtext({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-subtext', className)}>{children}</p>;
}

export function Body({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-body', className)}>{children}</p>;
}

export function Caption({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-caption', className)}>{children}</p>;
}

export function Label({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-label', className)}>{children}</p>;
}

export function NumberLg({ children, className }: PropsWithChildren<{ className?: string }>) {
  return <p className={cn('ds-number-lg', className)}>{children}</p>;
}
