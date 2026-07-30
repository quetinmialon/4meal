import Ajv from 'ajv'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..')
const schema = JSON.parse(fs.readFileSync(path.join(root, 'docs/supmeal/supmeal-1.0.schema.json'), 'utf8'))
const loadExample = (name: string) => JSON.parse(fs.readFileSync(path.join(root, `docs/supmeal/examples/${name}`), 'utf8'))

const ajv = new Ajv({ allErrors: true })
ajv.addFormat('date-time', /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/)
ajv.addFormat('uri', /^(?:https?|ftp):\/\/[^\s]+$/)
const validateSchema = ajv.compile(schema)

function validateIntegrity(document: {
  cookbooks: Array<{ id: string; recipe_ids: string[] }>
  recipes: Array<{ id: string; cookbook_ids: string[] }>
}): boolean {
  const cookbookIds = new Set(document.cookbooks.map((cookbook) => cookbook.id))
  const recipeIds = new Set(document.recipes.map((recipe) => recipe.id))

  return cookbookIds.size === document.cookbooks.length
    && recipeIds.size === document.recipes.length
    && document.cookbooks.every((cookbook) => cookbook.recipe_ids.every((id) => recipeIds.has(id)))
    && document.recipes.every((recipe) => recipe.cookbook_ids.every((id) => cookbookIds.has(id)))
    && document.cookbooks.every((cookbook) => cookbook.recipe_ids.every((recipeId) =>
      document.recipes.find((recipe) => recipe.id === recipeId)?.cookbook_ids.includes(cookbook.id) ?? false,
    ))
    && document.recipes.every((recipe) => recipe.cookbook_ids.every((cookbookId) =>
      document.cookbooks.find((cookbook) => cookbook.id === cookbookId)?.recipe_ids.includes(recipe.id) ?? false,
    ))
}

describe('SUPMEAL 1.0 JSON Schema', () => {
  it.each(['valid-minimal.json', 'valid-complete.json'])('accepts %s', (file) => {
    const document = loadExample(file)
    expect(validateSchema(document), JSON.stringify(validateSchema.errors)).toBe(true)
    expect(validateIntegrity(document)).toBe(true)
  })

  it('rejects an unknown format version', () => {
    const document = loadExample('invalid-unknown-version.json')
    expect(validateSchema(document)).toBe(false)
    expect(validateSchema.errors?.some((error) => error.instancePath === '/version')).toBe(true)
  })

  it('rejects a structurally valid document with dangling or asymmetric references', () => {
    const document = loadExample('invalid-reference.json')
    expect(validateSchema(document), JSON.stringify(validateSchema.errors)).toBe(true)
    expect(validateIntegrity(document)).toBe(false)
  })

  it('rejects unsupported properties and missing required recipe data', () => {
    const document = loadExample('valid-minimal.json')
    document.unexpected = true
    delete document.recipes[0].ingredients
    expect(validateSchema(document)).toBe(false)
  })
})
