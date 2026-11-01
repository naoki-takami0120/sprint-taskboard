export const STATUS_LABELS = {
  todo: 'To do',
  in_progress: 'In progress',
  done: 'Done',
}

// Only used to decide which buttons to show. The server enforces the real rules.
export const TRANSITIONS = {
  todo: ['in_progress'],
  in_progress: ['todo', 'done'],
  done: ['in_progress'],
}
