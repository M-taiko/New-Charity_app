import type { Routes } from '@angular/router';

export const CHAT_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/chat/chat.component').then((m) => m.ChatPage) },
];
