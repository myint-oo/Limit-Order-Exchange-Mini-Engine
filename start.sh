#!/bin/bash

echo "🚀 Starting Order Exchange Mini Engine..."

# Kill background processes on exit
trap 'kill $(jobs -p) 2>/dev/null' EXIT

# Start Laravel API server
echo "📦 Starting Laravel API on http://localhost:8000"
cd api && php artisan serve &

# Start Vue frontend
echo "🎨 Starting Vue Frontend on http://localhost:5173"
cd frontend && npm run dev &

echo ""
echo "✅ All servers started!"
echo "   API:      http://localhost:8000"
echo "   Frontend: http://localhost:5173"
echo ""
echo "Press Ctrl+C to stop all servers"

# Wait for all background processes
wait
